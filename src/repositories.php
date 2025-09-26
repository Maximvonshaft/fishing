<?php

declare(strict_types=1);


require_once __DIR__ . '/support.php';

function list_countries_summary(): array
{
    $stmt = db()->query('SELECT id, code, name FROM countries ORDER BY code');
    $countries = [];
    $warnMinutes = (int) config('app.warn_minutes', 120);

    foreach ($stmt->fetchAll() as $row) {
        $countryId = (int) $row['id'];

        $shipmentCountStmt = db()->prepare('SELECT COUNT(*) FROM shipments WHERE country_id = ? AND created_at >= datetime("now", "-30 days")');
        $shipmentCountStmt->execute([$countryId]);
        $shipmentCount = (int) $shipmentCountStmt->fetchColumn();

        $metricsStmt = db()->prepare('SELECT
            SUM(CASE WHEN sn.sla_status = "WARN" THEN 1 ELSE 0 END) AS warn_count,
            SUM(CASE WHEN sn.sla_status = "BREACH" THEN 1 ELSE 0 END) AS breach_count
            FROM shipment_nodes sn
            JOIN shipments s ON s.id = sn.shipment_id
            WHERE s.country_id = ?');
        $metricsStmt->execute([$countryId]);
        $metrics = $metricsStmt->fetch() ?: ['warn_count' => 0, 'breach_count' => 0];

        $templateUpdatedStmt = db()->prepare('SELECT MAX(updated_at) AS last_updated FROM country_nodes WHERE country_id = ?');
        $templateUpdatedStmt->execute([$countryId]);
        $templateUpdated = $templateUpdatedStmt->fetchColumn();
        $versionLabel = $templateUpdated ? 'rev ' . (new DateTimeImmutable($templateUpdated))->format('Ymd') : '未配置';

        $countries[] = [
            'id' => $countryId,
            'code' => $row['code'],
            'name' => $row['name'],
            'shipment_count' => $shipmentCount,
            'due_nodes' => (int) ($metrics['warn_count'] ?? 0),
            'overdue_nodes' => (int) ($metrics['breach_count'] ?? 0),
            'template_version' => $versionLabel,
            'warn_minutes' => $warnMinutes,
        ];
    }

    return $countries;
}

function get_projects_summary(): array
{
    return list_countries_summary();
}

function get_countries_for_select(): array
{
    $stmt = db()->query('SELECT id, code, name FROM countries ORDER BY code');
    return $stmt->fetchAll();
}

function get_country(int $countryId): ?array
{
    $stmt = db()->prepare('SELECT id, code, name FROM countries WHERE id = ?');
    $stmt->execute([$countryId]);
    $country = $stmt->fetch();
    if (!$country) {
        return null;
    }
    return [
        'id' => (int) $country['id'],
        'code' => $country['code'],
        'name' => $country['name'],
    ];
}

function get_country_by_code(string $code): ?array
{
    $stmt = db()->prepare('SELECT id, code, name FROM countries WHERE code = ?');
    $stmt->execute([$code]);
    $country = $stmt->fetch();
    if (!$country) {
        return null;
    }
    return [
        'id' => (int) $country['id'],
        'code' => $country['code'],
        'name' => $country['name'],
    ];
}

function create_country(string $code, string $name): int
{
    $code = strtoupper(trim($code));
    $name = trim($name);

    if ($code === '' || $name === '') {
        throw new RuntimeException('国家代码和名称不能为空');
    }

    if (!preg_match('/^[A-Z0-9\-]{2,8}$/', $code)) {
        throw new RuntimeException('国家代码需为 2-8 位大写字母/数字/短横线');
    }

    $existing = db()->prepare('SELECT id FROM countries WHERE code = ?');
    $existing->execute([$code]);
    if ($existing->fetchColumn()) {
        throw new RuntimeException('国家代码已存在');
    }

    $stmt = db()->prepare('INSERT INTO countries (code, name, created_at, updated_at) VALUES (?, ?, datetime("now"), datetime("now"))');
    $stmt->execute([$code, $name]);

    return (int) db()->lastInsertId();
}

function get_country_nodes(int $countryId): array
{
    $stmt = db()->prepare('SELECT cn.*, v.name AS vendor_name, u.display_name AS assignee_name
        FROM country_nodes cn
        JOIN vendors v ON v.id = cn.vendor_id
        LEFT JOIN users u ON u.id = cn.default_assignee_user_id
        WHERE cn.country_id = ?
        ORDER BY cn.sort_order');
    $stmt->execute([$countryId]);
    return $stmt->fetchAll();
}

function save_country_nodes(int $countryId, array $nodes): void
{
    $country = get_country($countryId);
    if (!$country) {
        throw new RuntimeException('国家不存在');
    }

    $normalized = [];
    foreach ($nodes as $node) {
        $normalized[] = [
            'id' => isset($node['id']) ? (int) $node['id'] : null,
            'sort_order' => (int) ($node['sort_order'] ?? 0),
            'name' => trim((string) ($node['name'] ?? '')),
            'vendor_id' => (int) ($node['vendor_id'] ?? 0),
            'default_assignee_user_id' => isset($node['default_assignee_user_id']) && $node['default_assignee_user_id'] !== ''
                ? (int) $node['default_assignee_user_id']
                : null,
            'base_type' => (string) ($node['base_type'] ?? ''),
            'sla_hours' => is_numeric($node['sla_hours'] ?? null) ? (float) $node['sla_hours'] : -1,
            'evidence_required' => (int) ($node['evidence_required'] ?? 0) === 1 ? 1 : 0,
            'signature_required' => (int) ($node['signature_required'] ?? 0) === 1 ? 1 : 0,
        ];
    }

    if (empty($normalized)) {
        throw new RuntimeException('至少需要一个节点');
    }

    usort($normalized, static fn(array $a, array $b): int => $a['sort_order'] <=> $b['sort_order']);

    $sortOrders = array_column($normalized, 'sort_order');
    if (count($sortOrders) !== count(array_unique($sortOrders))) {
        throw new RuntimeException('节点顺序不能重复');
    }

    if ($normalized[0]['base_type'] === 'previous') {
        throw new RuntimeException('首节点起算点不能为 previous');
    }

    $allowedBaseTypes = ['previous', 'eta', 'creation'];

    $vendorStmt = db()->prepare('SELECT id, active FROM vendors WHERE id = ?');
    $userStmt = db()->prepare('SELECT id, vendor_id, active FROM users WHERE id = ?');

    foreach ($normalized as $node) {
        if ($node['name'] === '') {
            throw new RuntimeException('节点名称不能为空');
        }
        if (!in_array($node['base_type'], $allowedBaseTypes, true)) {
            throw new RuntimeException('起算点类型不支持');
        }
        if ($node['sla_hours'] < 0) {
            throw new RuntimeException('时效必须为非负数');
        }

        $vendorStmt->execute([$node['vendor_id']]);
        $vendor = $vendorStmt->fetch();
        if (!$vendor || (int) $vendor['active'] !== 1) {
            throw new RuntimeException('供应商不存在或已停用');
        }

        if ($node['default_assignee_user_id'] !== null) {
            $userStmt->execute([$node['default_assignee_user_id']]);
            $user = $userStmt->fetch();
            if (!$user || (int) $user['active'] !== 1 || (int) $user['vendor_id'] !== $node['vendor_id']) {
                throw new RuntimeException('默认账号必须属于所选供应商并保持启用');
            }
        }
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $existingStmt = $pdo->prepare('SELECT id FROM country_nodes WHERE country_id = ?');
        $existingStmt->execute([$countryId]);
        $existingIds = array_map(static fn(array $row): int => (int) $row['id'], $existingStmt->fetchAll());

        $keepIds = [];
        $now = gmdate('Y-m-d H:i:s');

        $updateStmt = $pdo->prepare('UPDATE country_nodes SET sort_order = ?, name = ?, vendor_id = ?, default_assignee_user_id = ?, base_type = ?, sla_hours = ?, evidence_required = ?, signature_required = ?, updated_at = ? WHERE id = ?');
        $insertStmt = $pdo->prepare('INSERT INTO country_nodes (country_id, sort_order, name, vendor_id, default_assignee_user_id, base_type, sla_hours, evidence_required, signature_required, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

        foreach ($normalized as $node) {
            if ($node['id']) {
                $updateStmt->execute([
                    $node['sort_order'],
                    $node['name'],
                    $node['vendor_id'],
                    $node['default_assignee_user_id'],
                    $node['base_type'],
                    $node['sla_hours'],
                    $node['evidence_required'],
                    $node['signature_required'],
                    $now,
                    $node['id'],
                ]);
                $keepIds[] = $node['id'];
            } else {
                $insertStmt->execute([
                    $countryId,
                    $node['sort_order'],
                    $node['name'],
                    $node['vendor_id'],
                    $node['default_assignee_user_id'],
                    $node['base_type'],
                    $node['sla_hours'],
                    $node['evidence_required'],
                    $node['signature_required'],
                    $now,
                    $now,
                ]);
                $keepIds[] = (int) $pdo->lastInsertId();
            }
        }

        $removeIds = array_diff($existingIds, $keepIds);
        if (!empty($removeIds)) {
            $placeholders = implode(',', array_fill(0, count($removeIds), '?'));
            $deleteStmt = $pdo->prepare('DELETE FROM country_nodes WHERE country_id = ? AND id IN (' . $placeholders . ')');
            $deleteStmt->execute(array_merge([$countryId], array_values($removeIds)));
        }

        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function get_shipments(?string $countryCode = null, array $filters = []): array
{
    $params = [];
    $conditions = [];

    if ($countryCode) {
        $country = get_country_by_code($countryCode);
        if (!$country) {
            return [];
        }
        $conditions[] = 's.country_id = ?';
        $params[] = $country['id'];
    }

    if (!empty($filters['status'])) {
        if ($filters['status'] === 'WARN') {
            $conditions[] = 'EXISTS (SELECT 1 FROM shipment_nodes sn WHERE sn.shipment_id = s.id AND sn.sla_status = "WARN")';
        } elseif ($filters['status'] === 'BREACH') {
            $conditions[] = 'EXISTS (SELECT 1 FROM shipment_nodes sn WHERE sn.shipment_id = s.id AND sn.sla_status = "BREACH")';
        }
    }

    if (!empty($filters['q'])) {
        $conditions[] = 's.code LIKE ?';
        $params[] = '%' . $filters['q'] . '%';
    }

    $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

    $sql = 'SELECT s.*, c.code AS country_code, c.name AS country_name
        FROM shipments s
        JOIN countries c ON c.id = s.country_id
        ' . $where . '
        ORDER BY s.created_at DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    $rows = [];
    $metricsStmt = db()->prepare('SELECT COUNT(*) AS total, SUM(CASE WHEN status = "DONE" THEN 1 ELSE 0 END) AS done_count, SUM(CASE WHEN sla_status = "WARN" THEN 1 ELSE 0 END) AS warn_count, SUM(CASE WHEN sla_status = "BREACH" THEN 1 ELSE 0 END) AS breach_count FROM shipment_nodes WHERE shipment_id = ?');

    foreach ($stmt->fetchAll() as $row) {
        $metricsStmt->execute([$row['id']]);
        $metrics = $metricsStmt->fetch() ?: ['total' => 0, 'done_count' => 0, 'warn_count' => 0, 'breach_count' => 0];

        $rows[] = [
            'id' => (int) $row['id'],
            'code' => $row['code'],
            'origin' => $row['origin'],
            'destination' => $row['country_code'],
            'country_name' => $row['country_name'],
            'etd' => $row['created_at'],
            'eta' => $row['eta_dest_airport'],
            'progress' => (int) ($metrics['done_count'] ?? 0),
            'total_nodes' => (int) ($metrics['total'] ?? 0),
            'warn_nodes' => (int) ($metrics['warn_count'] ?? 0),
            'breach_nodes' => (int) ($metrics['breach_count'] ?? 0),
            'owner' => '',
            'updated_at' => $row['updated_at'],
            'status' => ($metrics['breach_count'] ?? 0) > 0 ? 'BREACH' : (($metrics['warn_count'] ?? 0) > 0 ? 'WARN' : 'OK'),
        ];
    }

    return $rows;
}

function get_shipment_detail(int $shipmentId, array $user): ?array
{
    $stmt = db()->prepare('SELECT s.*, c.code AS country_code, c.name AS country_name
        FROM shipments s
        JOIN countries c ON c.id = s.country_id
        WHERE s.id = ?');
    $stmt->execute([$shipmentId]);
    $shipment = $stmt->fetch();
    if (!$shipment) {
        return null;
    }

    $nodeStmt = db()->prepare('SELECT sn.*, v.name AS vendor_name, u.display_name AS assignee_name
        FROM shipment_nodes sn
        JOIN vendors v ON v.id = sn.vendor_id
        LEFT JOIN users u ON u.id = sn.assignee_user_id
        WHERE sn.shipment_id = ?
        ORDER BY sn.sort_order');
    $nodeStmt->execute([$shipmentId]);

    $nodes = [];
    foreach ($nodeStmt->fetchAll() as $node) {
        $nodes[] = format_node_for_user($node, $user);
    }

    return [
        'shipment' => $shipment,
        'nodes' => $nodes,
    ];
}

function get_node_files(int $nodeId): array
{
    $stmt = db()->prepare('SELECT id, file_name, mime_type, size_bytes, sha256, storage_path, uploaded_by, created_at
        FROM node_files WHERE node_id = ? ORDER BY created_at ASC');
    $stmt->execute([$nodeId]);
    $rows = $stmt->fetchAll();

    return array_map(static function (array $row): array {
        return [
            'id' => (int) $row['id'],
            'name' => $row['file_name'],
            'mime_type' => $row['mime_type'],
            'size_bytes' => (int) $row['size_bytes'],
            'size_label' => format_bytes((int) $row['size_bytes']),
            'sha256' => $row['sha256'],
            'sha256_prefix' => substr($row['sha256'], 0, 8),
            'download_url' => route('files.download', ['file_id' => (int) $row['id']]),
            'uploaded_at' => $row['created_at'],
        ];
    }, $rows);
}

function get_node_signatures(int $nodeId): array
{
    $stmt = db()->prepare('SELECT ns.*, u.display_name FROM node_signatures ns JOIN users u ON u.id = ns.user_id WHERE ns.node_id = ? ORDER BY ns.created_at ASC');
    $stmt->execute([$nodeId]);
    $rows = $stmt->fetchAll();

    return array_map(static function (array $row): array {
        return [
            'id' => (int) $row['id'],
            'user_id' => (int) $row['user_id'],
            'display_name' => $row['display_name'],
            'signer_name' => $row['signer_name'],
            'method' => $row['method'],
            'ip' => $row['ip'],
            'device' => $row['device'],
            'geo_lat' => $row['geo_lat'],
            'geo_lng' => $row['geo_lng'],
            'created_at' => $row['created_at'],
        ];
    }, $rows);
}

function count_node_files(int $nodeId, ?\PDO $pdo = null): int
{
    $pdo = $pdo ?? db();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM node_files WHERE node_id = ?');
    $stmt->execute([$nodeId]);
    return (int) $stmt->fetchColumn();
}

function count_node_signatures(int $nodeId, ?\PDO $pdo = null): int
{
    $pdo = $pdo ?? db();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM node_signatures WHERE node_id = ?');
    $stmt->execute([$nodeId]);
    return (int) $stmt->fetchColumn();
}

function format_node_for_user(array $node, array $user): array
{
    $canViewFull = $user['role'] === 'admin' || is_node_accessible_to_user($node, $user);

    if (!$canViewFull) {
        return [
            'id' => (int) $node['id'],
            'name' => $node['name'],
            'status' => $node['status'],
            'restricted' => true,
        ];
    }

    return [
        'id' => (int) $node['id'],
        'name' => $node['name'],
        'status' => $node['status'],
        'deadline' => $node['deadline_utc'],
        'remaining_minutes' => $node['remaining_minutes'] !== null ? (int) $node['remaining_minutes'] : null,
        'sla_status' => $node['sla_status'],
        'vendor_name' => $node['vendor_name'],
        'assignee_name' => $node['assignee_name'],
        'can_complete' => can_user_complete_node($node, $user),
        'actual_time' => $node['actual_time'],
        'base_type' => $node['base_type'] ?? 'eta',
        'sla_hours' => (float) $node['sla_hours'],
        'evidence_required' => (int) $node['evidence_required'] === 1,
        'signature_required' => (int) $node['signature_required'] === 1,
        'files' => get_node_files((int) $node['id']),
        'signatures' => get_node_signatures((int) $node['id']),
    ];
}

function is_node_accessible_to_user(array $node, array $user): bool
{
    if ($user['role'] === 'admin') {
        return true;
    }

    if ($user['role'] === 'vendor') {
        if ($node['assignee_user_id'] !== null && (int) $node['assignee_user_id'] === (int) $user['id']) {
            return true;
        }
        if ($node['assignee_user_id'] === null && isset($user['vendor_id']) && (int) $node['vendor_id'] === (int) $user['vendor_id']) {
            return true;
        }
    }

    return false;
}

function can_user_complete_node(array $node, array $user): bool
{
    if ($user['role'] !== 'vendor') {
        return false;
    }
    return is_node_accessible_to_user($node, $user) && $node['status'] !== 'DONE';
}

function fetch_user_tasks(array $user): array
{
    if ($user['role'] === 'admin') {
        $stmt = db()->query('SELECT sn.*, s.country_id, s.code, c.code AS country_code,
            (SELECT COUNT(*) FROM node_files nf WHERE nf.node_id = sn.id) AS evidence_count,
            (SELECT COUNT(*) FROM node_signatures ns WHERE ns.node_id = sn.id) AS signature_count
            FROM shipment_nodes sn
            JOIN shipments s ON s.id = sn.shipment_id
            JOIN countries c ON c.id = s.country_id
            WHERE sn.status = "PENDING"
            ORDER BY sn.deadline_utc IS NULL, sn.deadline_utc ASC');
    } else {
        $stmt = db()->prepare('SELECT sn.*, s.country_id, s.code, c.code AS country_code,
            (SELECT COUNT(*) FROM node_files nf WHERE nf.node_id = sn.id) AS evidence_count,
            (SELECT COUNT(*) FROM node_signatures ns WHERE ns.node_id = sn.id) AS signature_count
            FROM shipment_nodes sn
            JOIN shipments s ON s.id = sn.shipment_id
            JOIN countries c ON c.id = s.country_id
            WHERE sn.status = "PENDING" AND (sn.assignee_user_id = ? OR (sn.assignee_user_id IS NULL AND sn.vendor_id = ?))
            ORDER BY sn.deadline_utc IS NULL, sn.deadline_utc ASC');
        $stmt->execute([$user['id'], $user['vendor_id']]);
        return map_tasks($stmt->fetchAll());
    }

    return map_tasks($stmt->fetchAll());
}

function fetch_user_tasks_for_vendor(int $vendorId): array
{
    $stmt = db()->prepare('SELECT sn.*, s.country_id, s.code, c.code AS country_code,
        (SELECT COUNT(*) FROM node_files nf WHERE nf.node_id = sn.id) AS evidence_count,
        (SELECT COUNT(*) FROM node_signatures ns WHERE ns.node_id = sn.id) AS signature_count
        FROM shipment_nodes sn
        JOIN shipments s ON s.id = sn.shipment_id
        JOIN countries c ON c.id = s.country_id
        WHERE sn.status = "PENDING" AND (sn.assignee_user_id IN (SELECT id FROM users WHERE vendor_id = ?) OR sn.vendor_id = ?)
        ORDER BY sn.deadline_utc IS NULL, sn.deadline_utc ASC');
    $stmt->execute([$vendorId, $vendorId]);
    return map_tasks($stmt->fetchAll());
}

function map_tasks(array $rows): array
{
    return array_map(static function (array $row): array {
        $required = [];
        if ((int) ($row['evidence_required'] ?? 0) === 1 && (int) ($row['evidence_count'] ?? 0) === 0) {
            $required[] = 'UPLOAD_EVIDENCE';
        }
        if ((int) ($row['signature_required'] ?? 0) === 1 && (int) ($row['signature_count'] ?? 0) === 0) {
            $required[] = 'SIGN';
        }

        return [
            'node_id' => (int) $row['id'],
            'shipment_id' => (int) $row['shipment_id'],
            'country' => $row['country_code'] ?? '',
            'shipment_code' => $row['code'],
            'node_name' => $row['name'],
            'deadline_utc' => $row['deadline_utc'],
            'remaining_minutes' => $row['remaining_minutes'] !== null ? (int) $row['remaining_minutes'] : null,
            'sla_status' => $row['sla_status'],
            'required_actions' => $required,
        ];
    }, $rows);
}

function create_shipment(array $data, ?int $actorUserId = null): int
{
    $countryId = (int) ($data['country_id'] ?? 0);
    $code = trim((string) ($data['code'] ?? ''));
    $eta = (string) ($data['eta_dest_airport'] ?? '');

    if ($countryId <= 0 || !$code || !$eta) {
        throw new RuntimeException('国家、批次编码与 ETA 均为必填');
    }

    $country = get_country($countryId);
    if (!$country) {
        throw new RuntimeException('国家不存在');
    }

    $templateNodes = get_country_nodes($countryId);
    if (empty($templateNodes)) {
        throw new RuntimeException('请先为该国家配置节点模板');
    }

    if (!preg_match('/^[A-Z0-9\-]+$/i', $code)) {
        throw new RuntimeException('批次编码仅允许字母、数字和短横线');
    }

    $dupCheck = db()->prepare('SELECT id FROM shipments WHERE country_id = ? AND code = ?');
    $dupCheck->execute([$countryId, $code]);
    if ($dupCheck->fetchColumn()) {
        throw new RuntimeException('该国家下批次编码已存在');
    }

    try {
        $etaDt = new DateTimeImmutable($eta);
    } catch (\Throwable $e) {
        throw new RuntimeException('ETA 时间格式错误');
    }

    $etaUtc = $etaDt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $now = gmdate('Y-m-d H:i:s');
    $warnMinutes = (int) config('app.warn_minutes', 120);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO shipments (country_id, code, origin, eta_dest_airport, meta_json, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $countryId,
            $code,
            $data['origin'] ?? null,
            $etaUtc,
            isset($data['meta']) ? json_encode($data['meta'], JSON_UNESCAPED_UNICODE) : null,
            $now,
            $now,
        ]);
        $shipmentId = (int) $pdo->lastInsertId();

        $insertNode = $pdo->prepare('INSERT INTO shipment_nodes (shipment_id, country_node_id, name, sort_order, vendor_id, assignee_user_id, base_type, sla_hours, evidence_required, signature_required, status, deadline_utc, remaining_minutes, sla_status, actual_time, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

        foreach ($templateNodes as $node) {
            [$deadline, $remaining, $slaStatus] = initial_sla_state($node, $etaUtc, $now, $warnMinutes);
            $insertNode->execute([
                $shipmentId,
                $node['id'],
                $node['name'],
                $node['sort_order'],
                $node['vendor_id'],
                $node['default_assignee_user_id'],
                $node['base_type'],
                $node['sla_hours'],
                $node['evidence_required'],
                $node['signature_required'],
                'PENDING',
                $deadline,
                $remaining,
                $slaStatus,
                null,
                $now,
                $now,
            ]);

            $nodeId = (int) $pdo->lastInsertId();
            record_shipment_event($shipmentId, $nodeId, 'NODE_INSTANTIATED', [
                'sort_order' => (int) $node['sort_order'],
                'base_type' => $node['base_type'],
                'sla_hours' => (float) $node['sla_hours'],
            ]);
        }

        record_shipment_event($shipmentId, null, 'SHIPMENT_CREATED', [
            'country_id' => $countryId,
            'code' => $code,
            'eta' => $etaUtc,
            'created_at' => $now,
            'actor_user_id' => $actorUserId,
        ]);

        $pdo->commit();
        return $shipmentId;
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function initial_sla_state(array $templateNode, string $etaUtc, string $now, int $warnMinutes): array
{
    $baseType = $templateNode['base_type'];
    $slaHours = (float) $templateNode['sla_hours'];
    $start = null;

    if ($baseType === 'eta') {
        $start = $etaUtc;
    } elseif ($baseType === 'creation') {
        $start = $now;
    }

    if ($start === null) {
        return [null, null, 'NA'];
    }

    $deadline = (new DateTimeImmutable($start, new DateTimeZone('UTC')))->modify('+' . $slaHours . ' hours');
    $nowDt = new DateTimeImmutable($now, new DateTimeZone('UTC'));
    $remaining = (int) floor(($deadline->getTimestamp() - $nowDt->getTimestamp()) / 60);
    $status = $remaining < 0 ? 'BREACH' : ($remaining <= $warnMinutes ? 'WARN' : 'OK');

    return [$deadline->format('Y-m-d H:i:s'), $remaining, $status];
}

function record_shipment_event(int $shipmentId, ?int $nodeId, string $eventType, array $payload = []): void
{
    $stmt = db()->prepare('INSERT INTO shipment_events (shipment_id, node_id, event_type, payload_json, created_at) VALUES (?, ?, ?, ?, datetime("now"))');
    $stmt->execute([$shipmentId, $nodeId, $eventType, json_encode($payload, JSON_UNESCAPED_UNICODE)]);
}

function recalculate_pending_nodes(?int $shipmentId = null): void
{
    $warnMinutes = (int) config('app.warn_minutes', 120);
    $params = [];
    $where = '';
    if ($shipmentId) {
        $where = 'AND sn.shipment_id = ?';
        $params[] = $shipmentId;
    }

    $sql = 'SELECT sn.id, sn.shipment_id, sn.sort_order, sn.base_type, sn.sla_hours,
            s.eta_dest_airport, s.created_at
        FROM shipment_nodes sn
        JOIN shipments s ON s.id = sn.shipment_id
        WHERE sn.status = "PENDING" ' . $where;

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    $update = db()->prepare('UPDATE shipment_nodes SET deadline_utc = ?, remaining_minutes = ?, sla_status = ?, updated_at = datetime("now") WHERE id = ?');

    foreach ($stmt->fetchAll() as $row) {
        $start = null;
        if ($row['base_type'] === 'eta') {
            $start = $row['eta_dest_airport'];
        } elseif ($row['base_type'] === 'creation') {
            $start = $row['created_at'];
        } elseif ($row['base_type'] === 'previous') {
            $prev = db()->prepare('SELECT actual_time FROM shipment_nodes WHERE shipment_id = ? AND sort_order < ? ORDER BY sort_order DESC LIMIT 1');
            $prev->execute([$row['shipment_id'], $row['sort_order']]);
            $prevNode = $prev->fetch();
            if ($prevNode && $prevNode['actual_time']) {
                $start = $prevNode['actual_time'];
            }
        }

        if ($start === null) {
            $update->execute([null, null, 'NA', $row['id']]);
            continue;
        }

        $deadline = (new DateTimeImmutable($start, new DateTimeZone('UTC')))->modify('+' . (float) $row['sla_hours'] . ' hours');
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $remaining = (int) floor(($deadline->getTimestamp() - $now->getTimestamp()) / 60);
        $status = $remaining < 0 ? 'BREACH' : ($remaining <= $warnMinutes ? 'WARN' : 'OK');

        $update->execute([$deadline->format('Y-m-d H:i:s'), $remaining, $status, $row['id']]);
    }
}
