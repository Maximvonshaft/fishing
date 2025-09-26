<?php

declare(strict_types=1);


require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/repositories.php';

$action = $_POST['_action'] ?? $_GET['action'] ?? null;

if ($action === 'logout') {
    logout_user();
    redirect(route('auth.login'));
}

if ($action === 'download_file') {
    $user = require_login();
    $fileId = (int) ($_GET['file_id'] ?? 0);
    try {
        download_node_file($fileId, $user);
    } catch (RuntimeException $e) {
        http_response_code($e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 404);
        echo $e->getMessage();
    }
    exit;
}

if ($action === 'render_signature') {
    $user = require_login();
    $signatureId = (int) ($_GET['signature_id'] ?? 0);
    try {
        render_signature_image($signatureId, $user);
    } catch (RuntimeException $e) {
        http_response_code($e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 404);
        echo $e->getMessage();
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_post_action($action);
}

$page = $_GET['page'] ?? 'projects';
$user = current_user();

if ($page === 'login') {
    if ($user) {
        redirect($user['role'] === 'vendor' ? route('tasks.index') : route('projects.index'));
    }
    view('auth.login', ['title' => '登录', '_layout' => 'layouts.guest']);
    exit;
}

if (!$user) {
    redirect(route('auth.login'));
}

ensure_force_password_change();

switch ($page) {
    case 'first-setup':
        view('auth.first_setup', ['title' => '首次设置', '_layout' => 'layouts.guest', 'user' => $user]);
        break;
    case 'projects':
        require_role(['admin']);
        view('projects.index', [
            'title' => '国家目录',
            'projects' => get_projects_summary(),
            'user' => $user,
        ]);
        break;
    case 'templates':
        require_role(['admin']);
        $countries = get_countries_for_select();
        $selectedId = isset($_GET['country_id']) ? (int) $_GET['country_id'] : null;
        if ($selectedId === null && !empty($countries)) {
            $selectedId = (int) $countries[0]['id'];
        }
        $selectedCountry = $selectedId ? get_country($selectedId) : null;
        $nodes = $selectedCountry ? get_country_nodes($selectedCountry['id']) : [];
        $vendors = db()->query('SELECT id, name FROM vendors WHERE active = 1 ORDER BY name')->fetchAll();
        $vendorUsers = db()->query('SELECT id, display_name, vendor_id FROM users WHERE active = 1 ORDER BY display_name')->fetchAll();
        view('templates.index', [
            'title' => '模板配置',
            'countries' => $countries,
            'selectedCountry' => $selectedCountry,
            'nodes' => $nodes,
            'vendors' => $vendors,
            'vendorUsers' => $vendorUsers,
            'user' => $user,
        ]);
        break;
    case 'shipments':
        require_role(['admin']);
        $country = $_GET['country'] ?? null;
        $filters = [
            'status' => $_GET['status'] ?? null,
            'q' => $_GET['q'] ?? null,
        ];
        view('shipments.index', [
            'title' => '批次列表',
            'shipments' => get_shipments($country, $filters),
            'selectedProject' => $country,
            'filters' => $filters,
            'countries' => get_countries_for_select(),
            'user' => $user,
        ]);
        break;
    case 'shipment-detail':
        $shipmentId = (int) ($_GET['id'] ?? 0);
        $detail = get_shipment_detail($shipmentId, $user);
        if (!$detail) {
            http_response_code(404);
            echo 'Not found';
            break;
        }
        view('shipments.show', [
            'title' => '批次详情',
            'detail' => $detail,
            'user' => $user,
        ]);
        break;
    case 'tasks':
        $tasks = fetch_user_tasks($user);
        view('tasks.index', [
            'title' => '我的待办',
            'tasks' => $tasks,
            'user' => $user,
        ]);
        break;
    case 'node':
        $nodeId = (int) ($_GET['node_id'] ?? 0);
        $node = fetch_node_for_user($nodeId, $user);
        if (!$node) {
            http_response_code(403);
            echo 'Forbidden';
            break;
        }
        view('tasks.show', [
            'title' => '节点操作',
            'node' => $node,
            'user' => $user,
        ]);
        break;
    case 'vendors':
        $user = require_role(['admin']);
        $vendors = db()->query('SELECT * FROM vendors ORDER BY name')->fetchAll();
        view('settings.vendors', [
            'title' => '供应商管理',
            'vendors' => $vendors,
            'user' => $user,
        ]);
        break;
    case 'users':
        $user = require_role(['admin']);
        $users = db()->query('SELECT u.*, v.name AS vendor_name FROM users u LEFT JOIN vendors v ON v.id = u.vendor_id ORDER BY u.created_at DESC')->fetchAll();
        $vendors = db()->query('SELECT id, name FROM vendors WHERE active = 1 ORDER BY name')->fetchAll();
        view('settings.users', [
            'title' => '用户管理',
            'users' => $users,
            'vendors' => $vendors,
            'user' => $user,
        ]);
        break;
    case 'settings':
        $profile = $user;
        view('settings.profile', [
            'title' => '个人资料',
            'user' => $user,
            'profile' => $profile,
        ]);
        break;
    default:
        if ($user['role'] === 'admin') {
            redirect(route('projects.index'));
        }
        redirect(route('tasks.index'));
        break;
}

function handle_post_action(?string $action): void
{
    try {
        verify_csrf_token($_POST['_token'] ?? null);
    } catch (RuntimeException $e) {
        if ($action === 'login') {
            flash('error', '安全校验失败，请刷新页面重试');
            redirect(route('auth.login'));
        }
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }

    switch ($action) {
        case 'login':
            try {
                $user = authenticate($_POST['email'] ?? '', $_POST['password'] ?? '');
                if ((int) $user['force_password_reset'] === 1) {
                    redirect(route('auth.first_setup'));
                }
                redirect($user['role'] === 'vendor' ? route('tasks.index') : route('projects.index'));
            } catch (RuntimeException $e) {
                flash('error', $e->getMessage());
                redirect(route('auth.login'));
            }
            break;
        case 'complete_first_setup':
            $user = require_login();
            $newPassword = $_POST['new_password'] ?? '';
            $confirm = $_POST['new_password_confirmation'] ?? '';
            if ($newPassword !== $confirm || strlen($newPassword) < 8) {
                flash('error', '密码不符合要求');
                redirect(route('auth.first_setup'));
            }
            $hash = password_hash_secure($newPassword);
            $stmt = db()->prepare('UPDATE users SET password_hash = ?, force_password_reset = 0, password_changed_at = datetime("now"), updated_at = datetime("now") WHERE id = ?');
            $stmt->execute([$hash, $user['id']]);
            add_audit_event($user['id'], $user['id'], 'PASSWORD_CHANGED', []);
            flash('success', '密码更新成功');
            redirect($user['role'] === 'vendor' ? route('tasks.index') : route('projects.index'));
            break;
        case 'country_create':
            require_role(['admin']);
            try {
                create_country($_POST['code'] ?? '', $_POST['name'] ?? '');
                flash('success', '国家创建成功');
            } catch (RuntimeException $e) {
                flash('error', $e->getMessage());
            }
            redirect(route('projects.index'));
            break;
        case 'vendor_create':
            $user = require_role(['admin']);
            $name = trim($_POST['name'] ?? '');
            if ($name === '') {
                flash('error', '供应商名称不能为空');
                redirect(route('vendors.manage'));
            }
            $stmt = db()->prepare('INSERT INTO vendors (name, contact_email, contact_phone, active, created_at, updated_at) VALUES (?, ?, ?, 1, datetime("now"), datetime("now"))');
            $stmt->execute([$name, $_POST['contact_email'] ?? null, $_POST['contact_phone'] ?? null]);
            add_audit_event($user['id'], null, 'VENDOR_CREATED', ['name' => $name]);
            flash('success', '供应商创建成功');
            redirect(route('vendors.manage'));
            break;
        case 'vendor_toggle':
            $user = require_role(['admin']);
            $vendorId = (int) ($_POST['vendor_id'] ?? 0);
            $active = (int) ($_POST['active'] ?? 0);
            if ($active === 0) {
                $count = db()->prepare('SELECT COUNT(*) FROM users WHERE vendor_id = ? AND active = 1');
                $count->execute([$vendorId]);
                if ((int) $count->fetchColumn() > 0) {
                    flash('error', '停用前请先停用或转移该供应商下账号');
                    redirect(route('vendors.manage'));
                }
            }
            $stmt = db()->prepare('UPDATE vendors SET active = ?, updated_at = datetime("now") WHERE id = ?');
            $stmt->execute([$active, $vendorId]);
            add_audit_event($user['id'], null, 'VENDOR_TOGGLED', ['vendor_id' => $vendorId, 'active' => $active]);
            flash('success', '状态已更新');
            redirect(route('vendors.manage'));
            break;
        case 'country_nodes_save':
            require_role(['admin']);
            $countryId = (int) ($_POST['country_id'] ?? 0);
            $nodes = $_POST['nodes'] ?? [];
            if (!is_array($nodes)) {
                $nodes = [];
            }
            try {
                save_country_nodes($countryId, $nodes);
                flash('success', '模板已保存');
            } catch (RuntimeException $e) {
                flash('error', $e->getMessage());
            }
            redirect(route('templates.index', ['country_id' => $countryId]));
            break;
        case 'user_create':
            $user = require_role(['admin']);
            $email = trim($_POST['email'] ?? '');
            $display = trim($_POST['display_name'] ?? '');
            $role = $_POST['role'] ?? 'vendor';
            $vendorId = $_POST['vendor_id'] ?? null;
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash('error', '邮箱格式错误');
                redirect(route('users.manage'));
            }
            if ($display === '') {
                flash('error', '显示名称不能为空');
                redirect(route('users.manage'));
            }
            if ($role === 'vendor') {
                if (!$vendorId) {
                    flash('error', '供应商账号必须选择供应商');
                    redirect(route('users.manage'));
                }
                $vendorStmt = db()->prepare('SELECT id, active FROM vendors WHERE id = ?');
                $vendorStmt->execute([$vendorId]);
                $vendor = $vendorStmt->fetch();
                if (!$vendor || (int) $vendor['active'] !== 1) {
                    flash('error', '供应商不存在或已停用');
                    redirect(route('users.manage'));
                }
            } else {
                $vendorId = null;
            }
            $tempPassword = $_POST['temp_password'] ?? random_password();
            $hash = password_hash_secure($tempPassword);
            $stmt = db()->prepare('INSERT INTO users (vendor_id, email, display_name, password_hash, role, active, force_password_reset, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, 1, datetime("now"), datetime("now"))');
            $stmt->execute([$vendorId, mb_strtolower($email), $display, $hash, $role]);
            $newUserId = (int) db()->lastInsertId();
            add_audit_event($user['id'], $newUserId, 'USER_CREATED', ['email' => $email, 'role' => $role]);
            flash('success', '用户创建成功，临时密码：' . $tempPassword);
            redirect(route('users.manage'));
            break;
        case 'user_toggle':
            $user = require_role(['admin']);
            $targetId = (int) ($_POST['user_id'] ?? 0);
            $active = (int) ($_POST['active'] ?? 0);
            $stmt = db()->prepare('UPDATE users SET active = ?, updated_at = datetime("now") WHERE id = ?');
            $stmt->execute([$active, $targetId]);
            add_audit_event($user['id'], $targetId, 'USER_TOGGLED', ['active' => $active]);
            if ($targetId === ($_SESSION['user_id'] ?? null) && $active === 0) {
                logout_user();
                redirect(route('auth.login'));
            }
            flash('success', '账号状态已更新');
            redirect(route('users.manage'));
            break;
        case 'user_reset_password':
            $user = require_role(['admin']);
            $targetId = (int) ($_POST['user_id'] ?? 0);
            $tempPassword = random_password();
            $hash = password_hash_secure($tempPassword);
            $stmt = db()->prepare('UPDATE users SET password_hash = ?, force_password_reset = 1, updated_at = datetime("now") WHERE id = ?');
            $stmt->execute([$hash, $targetId]);
            add_audit_event($user['id'], $targetId, 'PASSWORD_RESET_ISSUED', []);
            flash('success', '临时密码：' . $tempPassword);
            redirect(route('users.manage'));
            break;
        case 'shipment_create':
            require_role(['admin']);
            $payload = [
                'country_id' => (int) ($_POST['country_id'] ?? 0),
                'code' => $_POST['code'] ?? '',
                'origin' => $_POST['origin'] ?? null,
                'eta_dest_airport' => $_POST['eta_dest_airport'] ?? '',
            ];
            $remarks = trim((string) ($_POST['remarks'] ?? ''));
            if ($remarks !== '') {
                $payload['meta'] = ['remarks' => $remarks];
            }
            try {
                $shipmentId = create_shipment($payload, $user['id']);
                flash('success', '批次创建成功');
                if (!empty($_POST['open_detail'])) {
                    redirect(route('shipments.show', ['id' => $shipmentId]));
                }
                $country = get_country($payload['country_id']);
                $countryCode = $country ? $country['code'] : null;
                redirect(route('shipments.index', $countryCode ? ['country' => $countryCode] : []));
            } catch (RuntimeException $e) {
                flash('error', $e->getMessage());
                redirect(route('shipments.index'));
            }
            break;
        case 'profile_update':
            $user = require_login();
            $display = trim($_POST['display_name'] ?? '');
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirm = $_POST['new_password_confirmation'] ?? '';
            if ($display !== '') {
                $stmt = db()->prepare('UPDATE users SET display_name = ?, updated_at = datetime("now") WHERE id = ?');
                $stmt->execute([$display, $user['id']]);
            }
            if ($newPassword !== '') {
                $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
                $stmt->execute([$user['id']]);
                $row = $stmt->fetch();
                if (!$row || !password_verify($currentPassword, $row['password_hash'])) {
                    flash('error', '当前密码错误');
                    redirect(route('settings.index'));
                }
                if ($newPassword !== $confirm || strlen($newPassword) < 8) {
                    flash('error', '新密码不符合要求');
                    redirect(route('settings.index'));
                }
                $hash = password_hash_secure($newPassword);
                $update = db()->prepare('UPDATE users SET password_hash = ?, force_password_reset = 0, password_changed_at = datetime("now"), updated_at = datetime("now") WHERE id = ?');
                $update->execute([$hash, $user['id']]);
                add_audit_event($user['id'], $user['id'], 'PASSWORD_CHANGED', []);
            }
            flash('success', '资料已更新');
            redirect(route('settings.index'));
            break;
        case 'node_complete':
            $user = require_login();
            $nodeId = (int) ($_POST['node_id'] ?? 0);
            $actualTime = $_POST['actual_time'] ?? '';
            try {
                complete_node($nodeId, $actualTime, $user, $_FILES, $_POST);
                flash('success', '节点已完成');
                redirect(route('tasks.index'));
            } catch (RuntimeException $e) {
                flash('error', $e->getMessage());
                redirect(route('tasks.show', ['node_id' => $nodeId]));
            }
            break;
        default:
            break;
    }
}

function fetch_node_for_user(int $nodeId, array $user): ?array
{
    $stmt = db()->prepare('SELECT sn.*, s.code AS shipment_code, s.eta_dest_airport, s.created_at AS shipment_created_at, c.code AS country_code, v.name AS vendor_name
        FROM shipment_nodes sn
        JOIN shipments s ON s.id = sn.shipment_id
        JOIN countries c ON c.id = s.country_id
        JOIN vendors v ON v.id = sn.vendor_id
        WHERE sn.id = ?');
    $stmt->execute([$nodeId]);
    $node = $stmt->fetch();
    if (!$node) {
        return null;
    }

    if (!is_node_accessible_to_user($node, $user) && $user['role'] !== 'admin') {
        return null;
    }

    $node['code'] = $node['shipment_code'];
    $node['country'] = $node['country_code'] ?? '';
    $node['files'] = get_node_files($nodeId);
    $node['signatures'] = get_node_signatures($nodeId);

    return $node;
}

function complete_node(int $nodeId, string $actualTime, array $user, array $files, array $post): void
{
    $node = fetch_node_for_user($nodeId, $user);
    if (!$node) {
        throw new RuntimeException('节点不存在或无权访问。');
    }

    if ($node['status'] === 'DONE') {
        throw new RuntimeException('该节点已完成，无需重复提交。');
    }

    if ($user['role'] !== 'admin' && !is_node_accessible_to_user($node, $user)) {
        throw new RuntimeException('您无权操作该节点。');
    }

    $prevStmt = db()->prepare('SELECT status FROM shipment_nodes WHERE shipment_id = ? AND sort_order < ? ORDER BY sort_order DESC LIMIT 1');
    $prevStmt->execute([$node['shipment_id'], $node['sort_order']]);
    $previous = $prevStmt->fetch();
    if ($previous && $previous['status'] !== 'DONE') {
        throw new RuntimeException('前序节点未完成，无法提交。');
    }

    try {
        $dt = new DateTimeImmutable($actualTime ?: 'now', new DateTimeZone('UTC'));
    } catch (Throwable $e) {
        throw new RuntimeException('实际完成时间格式错误，请填写有效的 UTC 时间。');
    }

    $actualUtc = $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $warnMinutes = (int) config('app.warn_minutes', 120);

    $uploads = normalize_uploads_array($files['evidences'] ?? null);
    $pdo = db();
    $storedFiles = [];

    $pdo->beginTransaction();
    try {
        foreach ($uploads as $upload) {
            if ((int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ((int) $upload['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('附件上传失败，请重试。');
            }
            $storedFiles[] = persist_node_file($pdo, $node, $upload, $user);
        }

        $signatureMethod = $post['signature_method'] ?? 'draw';
        $signaturePayload = '';
        if ($signatureMethod === 'draw') {
            $signaturePayload = trim((string) ($post['signature_draw_data'] ?? ''));
        } else {
            $signaturePayload = trim((string) ($post['signature_value'] ?? ''));
        }
        if ($signaturePayload !== '') {
            persist_node_signature($pdo, $node, $user, $signaturePayload, $signatureMethod);
        }

        if ((int) $node['evidence_required'] === 1 && count_node_files($nodeId, $pdo) === 0) {
            throw new RuntimeException('该节点要求上传附件，请先上传后再提交。');
        }

        if ((int) $node['signature_required'] === 1 && count_node_signatures($nodeId, $pdo) === 0) {
            throw new RuntimeException('该节点要求签名，请完成签署后再提交。');
        }

        $newSlaStatus = $node['sla_status'];
        $remainingAfter = $node['remaining_minutes'];

        if (!empty($node['deadline_utc'])) {
            $deadline = new DateTimeImmutable($node['deadline_utc'], new DateTimeZone('UTC'));
            $actual = new DateTimeImmutable($actualUtc, new DateTimeZone('UTC'));
            $remainingAfter = (int) floor(($deadline->getTimestamp() - $actual->getTimestamp()) / 60);
            $newSlaStatus = $remainingAfter < 0 ? 'BREACH' : ($remainingAfter <= $warnMinutes ? 'WARN' : 'OK');
        }

        $update = $pdo->prepare('UPDATE shipment_nodes SET status = "DONE", actual_time = ?, remaining_minutes = ?, sla_status = ?, updated_at = datetime("now") WHERE id = ?');
        $update->execute([$actualUtc, $remainingAfter, $newSlaStatus, $nodeId]);

        record_shipment_event((int) $node['shipment_id'], $nodeId, 'NODE_DONE', [
            'actual_time' => $actualUtc,
            'completed_by' => $user['id'],
        ]);

        $nextStmt = $pdo->prepare('SELECT * FROM shipment_nodes WHERE shipment_id = ? AND sort_order > ? ORDER BY sort_order ASC LIMIT 1');
        $nextStmt->execute([$node['shipment_id'], $node['sort_order']]);
        $nextNode = $nextStmt->fetch();
        if ($nextNode && $nextNode['base_type'] === 'previous') {
            $deadline = (new DateTimeImmutable($actualUtc, new DateTimeZone('UTC')))->modify('+' . (float) $nextNode['sla_hours'] . ' hours');
            $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            $remaining = (int) floor(($deadline->getTimestamp() - $now->getTimestamp()) / 60);
            $status = $remaining < 0 ? 'BREACH' : ($remaining <= $warnMinutes ? 'WARN' : 'OK');
            $updateNext = $pdo->prepare('UPDATE shipment_nodes SET deadline_utc = ?, remaining_minutes = ?, sla_status = ?, updated_at = datetime("now") WHERE id = ?');
            $updateNext->execute([$deadline->format('Y-m-d H:i:s'), $remaining, $status, $nextNode['id']]);
            record_shipment_event((int) $node['shipment_id'], (int) $nextNode['id'], 'SLA_RECALCULATED', [
                'trigger' => $nodeId,
                'deadline' => $deadline->format('Y-m-d H:i:s'),
                'remaining' => $remaining,
                'status' => $status,
            ]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        foreach ($storedFiles as $stored) {
            if (isset($stored['path']) && is_string($stored['path']) && is_file($stored['path'])) {
                @unlink($stored['path']);
            }
        }
        if ($e instanceof RuntimeException) {
            throw $e;
        }
        throw new RuntimeException('节点提交失败，请稍后再试。');
    }
}

function persist_node_file(PDO $pdo, array $node, array $upload, array $user): array
{
    $originalName = (string) ($upload['name'] ?? '');
    $filename = sanitize_uploaded_filename($originalName !== '' ? $originalName : 'attachment');
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $allowed = upload_allowed_extensions();
    if ($extension === '' || !in_array($extension, $allowed, true)) {
        throw new RuntimeException('附件类型不允许，请上传 pdf/jpg/png。');
    }

    $maxSize = (int) config('uploads.max_size', 20 * 1024 * 1024);
    $sizeBytes = (int) ($upload['size'] ?? 0);
    if ($sizeBytes <= 0 || $sizeBytes > $maxSize) {
        throw new RuntimeException('附件大小超出限制 (<= 20MB)。');
    }

    $tmpPath = (string) ($upload['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        throw new RuntimeException('附件上传失败，请重试。');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($tmpPath) ?: ($upload['type'] ?? 'application/octet-stream');
    $allowedMime = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
    ];
    $validMimes = $allowedMime[$extension] ?? [];
    if (!in_array($mimeType, $validMimes, true)) {
        throw new RuntimeException('附件类型与内容不匹配，请检查文件。');
    }

    $hash = hash_file('sha256', $tmpPath);
    $relativeDir = date('Y/m');
    $baseDir = rtrim(uploads_directory(), '/');
    $targetDir = $baseDir . '/' . $relativeDir;
    ensure_directory($targetDir);

    $randomName = bin2hex(random_bytes(16));
    $storageRelative = $relativeDir . '/' . $randomName . '.' . $extension;
    $storagePath = $baseDir . '/' . $storageRelative;

    if (!move_uploaded_file($tmpPath, $storagePath)) {
        throw new RuntimeException('附件保存失败，请重试。');
    }

    $stmt = $pdo->prepare('INSERT INTO node_files (node_id, file_name, mime_type, size_bytes, sha256, storage_path, uploaded_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, datetime("now"))');
    $stmt->execute([
        $node['id'],
        $filename,
        $mimeType,
        $sizeBytes,
        $hash,
        $storageRelative,
        $user['id'],
    ]);
    $fileId = (int) $pdo->lastInsertId();

    record_shipment_event((int) $node['shipment_id'], (int) $node['id'], 'EVIDENCE_UPLOADED', [
        'file_id' => $fileId,
        'file_name' => $filename,
        'sha256' => $hash,
        'uploaded_by' => $user['id'],
    ]);

    return ['id' => $fileId, 'path' => $storagePath];
}

function persist_node_signature(PDO $pdo, array $node, array $user, string $signatureValue, string $method): void
{
    $method = in_array($method, ['pin', 'draw'], true) ? $method : 'draw';
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $device = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $signer = $user['display_name'] ?? ($user['email'] ?? '签署人');
    $imagePath = null;
    $imageSha = null;
    $imageBytes = null;

    if ($method === 'draw') {
        $payload = trim($signatureValue);
        if ($payload === '') {
            throw new RuntimeException('请在签名板内手写签名后再提交。');
        }
        if (!str_starts_with($payload, 'data:image/png;base64,')) {
            throw new RuntimeException('签名数据格式无效，请重新签署。');
        }
        $base64 = substr($payload, strlen('data:image/png;base64,'));
        $binary = base64_decode($base64, true);
        if ($binary === false || $binary === '') {
            throw new RuntimeException('签名数据无法解析，请重新签署。');
        }
        if (strlen($binary) > (int) config('uploads.max_size', 20 * 1024 * 1024)) {
            throw new RuntimeException('签名图像超出大小限制，请重试。');
        }
        if (strlen($binary) < 256) {
            throw new RuntimeException('检测到签名笔画过少，请重新签署。');
        }

        $hash = hash('sha256', $binary);
        $relativeDir = 'signatures/' . date('Y/m');
        $baseDir = rtrim(uploads_directory(), '/');
        $targetDir = $baseDir . '/' . $relativeDir;
        ensure_directory($targetDir);

        $filename = bin2hex(random_bytes(16)) . '.png';
        $storageRelative = $relativeDir . '/' . $filename;
        $storagePath = $baseDir . '/' . $storageRelative;
        if (file_put_contents($storagePath, $binary) === false) {
            throw new RuntimeException('签名保存失败，请重试。');
        }

        $imagePath = $storageRelative;
        $imageSha = $hash;
        $imageBytes = strlen($binary);
    } else {
        $value = trim($signatureValue);
        if ($value !== '') {
            $signer = function_exists('mb_substr') ? mb_substr($value, 0, 120) : substr($value, 0, 120);
        }
    }

    $stmt = $pdo->prepare('INSERT INTO node_signatures (node_id, user_id, method, signer_name, ip, device, geo_lat, geo_lng, image_path, image_sha256, image_bytes, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NULL, NULL, ?, ?, ?, datetime("now"))');
    $stmt->execute([
        $node['id'],
        $user['id'],
        $method,
        $signer,
        $ip,
        $device,
        $imagePath,
        $imageSha,
        $imageBytes,
    ]);

    record_shipment_event((int) $node['shipment_id'], (int) $node['id'], 'SIGNATURE_CAPTURED', [
        'user_id' => $user['id'],
        'method' => $method,
        'signer_name' => $signer,
        'ip' => $ip,
        'image_sha256' => $imageSha,
    ]);
}

function download_node_file(int $fileId, array $user): void
{
    if ($fileId <= 0) {
        throw new RuntimeException('附件不存在。', 404);
    }

    $stmt = db()->prepare('SELECT nf.*, sn.vendor_id, sn.assignee_user_id, sn.shipment_id FROM node_files nf
        JOIN shipment_nodes sn ON sn.id = nf.node_id WHERE nf.id = ?');
    $stmt->execute([$fileId]);
    $file = $stmt->fetch();
    if (!$file) {
        throw new RuntimeException('附件不存在。', 404);
    }

    if ($user['role'] !== 'admin' && !is_node_accessible_to_user($file, $user)) {
        throw new RuntimeException('无权限下载该附件。', 403);
    }

    $baseDir = rtrim(uploads_directory(), '/');
    $storagePath = $baseDir . '/' . ltrim((string) $file['storage_path'], '/');
    if (!is_file($storagePath)) {
        throw new RuntimeException('附件文件不存在或已被删除。', 404);
    }

    header('Content-Type: ' . $file['mime_type']);
    header('Content-Length: ' . $file['size_bytes']);
    header('Content-Disposition: attachment; filename="' . rawurlencode($file['file_name']) . '"');
    header('X-Content-Type-Options: nosniff');

    $handle = fopen($storagePath, 'rb');
    if ($handle === false) {
        throw new RuntimeException('附件读取失败。', 500);
    }
    while (!feof($handle)) {
        echo fread($handle, 8192);
    }
    fclose($handle);

    record_shipment_event((int) $file['shipment_id'], (int) $file['node_id'], 'EVIDENCE_DOWNLOADED', [
        'file_id' => $fileId,
        'downloaded_by' => $user['id'],
    ]);
}

function render_signature_image(int $signatureId, array $user): void
{
    if ($signatureId <= 0) {
        throw new RuntimeException('签名不存在。', 404);
    }

    $stmt = db()->prepare('SELECT ns.*, sn.shipment_id FROM node_signatures ns JOIN shipment_nodes sn ON sn.id = ns.node_id WHERE ns.id = ?');
    $stmt->execute([$signatureId]);
    $signature = $stmt->fetch();
    if (!$signature) {
        throw new RuntimeException('签名不存在。', 404);
    }

    if ($user['role'] !== 'admin') {
        $node = fetch_node_for_user((int) $signature['node_id'], $user);
        if (!$node) {
            throw new RuntimeException('无权访问该签名。', 403);
        }
    }

    $imagePath = $signature['image_path'] ?? null;
    if (!$imagePath) {
        throw new RuntimeException('该签名没有图像记录。', 404);
    }

    $fullPath = rtrim(uploads_directory(), '/') . '/' . ltrim((string) $imagePath, '/');
    if (!is_file($fullPath)) {
        throw new RuntimeException('签名文件不存在。', 404);
    }

    header('Content-Type: image/png');
    header('Content-Length: ' . (string) filesize($fullPath));
    header('Content-Disposition: inline; filename="signature-' . $signatureId . '.png"');
    header('Cache-Control: private, max-age=31536000');
    header('X-Content-Type-Options: nosniff');

    $handle = fopen($fullPath, 'rb');
    if ($handle === false) {
        throw new RuntimeException('签名读取失败。', 500);
    }
    while (!feof($handle)) {
        echo fread($handle, 8192);
    }
    fclose($handle);

    record_shipment_event((int) $signature['shipment_id'], (int) $signature['node_id'], 'SIGNATURE_VIEWED', [
        'signature_id' => $signatureId,
        'viewed_by' => $user['id'],
    ]);
}
