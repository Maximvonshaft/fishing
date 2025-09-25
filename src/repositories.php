<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';

function get_projects_summary(): array
{
    $stmt = db()->query('SELECT country AS code, country AS name, COUNT(*) AS shipment_count FROM shipments GROUP BY country ORDER BY country');
    $projects = [];
    foreach ($stmt->fetchAll() as $row) {
        $metrics = db()->prepare('SELECT SUM(CASE WHEN sla_status = "WARN" THEN 1 ELSE 0 END) AS warn_count, SUM(CASE WHEN sla_status = "BREACH" THEN 1 ELSE 0 END) AS breach_count FROM shipment_nodes sn JOIN shipments s ON s.id = sn.shipment_id WHERE s.country = ?');
        $metrics->execute([$row['code']]);
        $counts = $metrics->fetch();
        $projects[] = [
            'code' => $row['code'],
            'name' => $row['name'],
            'shipment_count' => (int) $row['shipment_count'],
            'due_nodes' => (int) ($counts['warn_count'] ?? 0),
            'overdue_nodes' => (int) ($counts['breach_count'] ?? 0),
            'template_version' => 'v1.0',
        ];
    }
    return $projects;
}

function get_shipments(?string $country = null): array
{
    if ($country) {
        $stmt = db()->prepare('SELECT * FROM shipments WHERE country = ? ORDER BY created_at DESC');
        $stmt->execute([$country]);
    } else {
        $stmt = db()->query('SELECT * FROM shipments ORDER BY created_at DESC');
    }

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $nodeMetrics = db()->prepare('SELECT COUNT(*) AS total, SUM(CASE WHEN sla_status = "WARN" THEN 1 ELSE 0 END) AS warn_count, SUM(CASE WHEN sla_status = "BREACH" THEN 1 ELSE 0 END) AS breach_count, SUM(CASE WHEN status = "DONE" THEN 1 ELSE 0 END) AS done_count FROM shipment_nodes WHERE shipment_id = ?');
        $nodeMetrics->execute([$row['id']]);
        $metrics = $nodeMetrics->fetch();
        $rows[] = [
            'id' => $row['id'],
            'code' => $row['code'],
            'origin' => $row['origin'],
            'destination' => $row['country'],
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
    $stmt = db()->prepare('SELECT * FROM shipments WHERE id = ?');
    $stmt->execute([$shipmentId]);
    $shipment = $stmt->fetch();
    if (!$shipment) {
        return null;
    }

    $nodeStmt = db()->prepare('SELECT sn.*, v.name AS vendor_name, u.display_name AS assignee_name FROM shipment_nodes sn JOIN vendors v ON v.id = sn.vendor_id LEFT JOIN users u ON u.id = sn.assignee_user_id WHERE sn.shipment_id = ? ORDER BY sn.sort_order');
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

function format_node_for_user(array $node, array $user): array
{
    $canViewFull = $user['role'] === 'admin' || is_node_accessible_to_user($node, $user);

    if (!$canViewFull) {
        return [
            'id' => $node['id'],
            'name' => $node['name'],
            'status' => $node['status'],
            'restricted' => true,
        ];
    }

    return [
        'id' => $node['id'],
        'name' => $node['name'],
        'status' => $node['status'],
        'deadline' => $node['deadline_utc'],
        'remaining_minutes' => $node['remaining_minutes'],
        'sla_status' => $node['sla_status'],
        'vendor_name' => $node['vendor_name'],
        'assignee_name' => $node['assignee_name'],
        'can_complete' => can_user_complete_node($node, $user),
        'actual_time' => $node['actual_time'],
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
        if ($node['assignee_user_id'] === null && (int) $node['vendor_id'] === (int) $user['vendor_id']) {
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
        $stmt = db()->query('SELECT sn.*, s.country, s.code FROM shipment_nodes sn JOIN shipments s ON s.id = sn.shipment_id WHERE sn.status = "PENDING" ORDER BY sn.deadline_utc ASC');
    } else {
        $stmt = db()->prepare('SELECT sn.*, s.country, s.code FROM shipment_nodes sn JOIN shipments s ON s.id = sn.shipment_id WHERE sn.status = "PENDING" AND (sn.assignee_user_id = ? OR (sn.assignee_user_id IS NULL AND sn.vendor_id = ?)) ORDER BY sn.deadline_utc ASC');
        $stmt->execute([$user['id'], $user['vendor_id']]);
        return map_tasks($stmt->fetchAll());
    }

    return map_tasks($stmt->fetchAll());
}

function fetch_user_tasks_for_vendor(int $vendorId): array
{
    $stmt = db()->prepare('SELECT sn.*, s.country, s.code FROM shipment_nodes sn JOIN shipments s ON s.id = sn.shipment_id WHERE sn.status = "PENDING" AND (sn.assignee_user_id IN (SELECT id FROM users WHERE vendor_id = ?) OR sn.vendor_id = ?) ORDER BY sn.deadline_utc ASC');
    $stmt->execute([$vendorId, $vendorId]);
    return map_tasks($stmt->fetchAll());
}

function map_tasks(array $rows): array
{
    return array_map(static function (array $row): array {
        return [
            'node_id' => $row['id'],
            'shipment_id' => $row['shipment_id'],
            'country' => $row['country'],
            'shipment_code' => $row['code'],
            'node_name' => $row['name'],
            'deadline_utc' => $row['deadline_utc'],
            'remaining_minutes' => $row['remaining_minutes'],
            'sla_status' => $row['sla_status'],
        ];
    }, $rows);
}

