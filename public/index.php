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
            complete_node($nodeId, $actualTime, $user);
            flash('success', '节点已完成');
            redirect(route('tasks.index'));
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

    return $node;
}

function complete_node(int $nodeId, string $actualTime, array $user): void
{
    $node = fetch_node_for_user($nodeId, $user);
    if (!$node) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }

    if ($node['status'] === 'DONE') {
        return;
    }

    if ($user['role'] !== 'admin' && !is_node_accessible_to_user($node, $user)) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }

    $prevStmt = db()->prepare('SELECT status FROM shipment_nodes WHERE shipment_id = ? AND sort_order < ? ORDER BY sort_order DESC LIMIT 1');
    $prevStmt->execute([$node['shipment_id'], $node['sort_order']]);
    $previous = $prevStmt->fetch();
    if ($previous && $previous['status'] !== 'DONE') {
        http_response_code(409);
        echo '前序节点未完成';
        exit;
    }

    try {
        $dt = new DateTimeImmutable($actualTime ?: 'now', new DateTimeZone('UTC'));
    } catch (Throwable $e) {
        http_response_code(422);
        echo '时间格式错误';
        exit;
    }

    $actualUtc = $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $warnMinutes = (int) config('app.warn_minutes', 120);
    $newSlaStatus = $node['sla_status'];
    $remainingAfter = $node['remaining_minutes'];

    if (!empty($node['deadline_utc'])) {
        $deadline = new DateTimeImmutable($node['deadline_utc'], new DateTimeZone('UTC'));
        $actual = new DateTimeImmutable($actualUtc, new DateTimeZone('UTC'));
        $remainingAfter = (int) floor(($deadline->getTimestamp() - $actual->getTimestamp()) / 60);
        $newSlaStatus = $remainingAfter < 0 ? 'BREACH' : ($remainingAfter <= $warnMinutes ? 'WARN' : 'OK');
    }

    $update = db()->prepare('UPDATE shipment_nodes SET status = "DONE", actual_time = ?, remaining_minutes = ?, sla_status = ?, updated_at = datetime("now") WHERE id = ?');
    $update->execute([$actualUtc, $remainingAfter, $newSlaStatus, $nodeId]);

    record_shipment_event((int) $node['shipment_id'], $nodeId, 'NODE_DONE', [
        'actual_time' => $actualUtc,
        'completed_by' => $user['id'],
    ]);

    $nextStmt = db()->prepare('SELECT * FROM shipment_nodes WHERE shipment_id = ? AND sort_order > ? ORDER BY sort_order ASC LIMIT 1');
    $nextStmt->execute([$node['shipment_id'], $node['sort_order']]);
    $nextNode = $nextStmt->fetch();
    if ($nextNode && $nextNode['base_type'] === 'previous') {
        $deadline = (new DateTimeImmutable($actualUtc, new DateTimeZone('UTC')))->modify('+' . (float) $nextNode['sla_hours'] . ' hours');
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $remaining = (int) floor(($deadline->getTimestamp() - $now->getTimestamp()) / 60);
        $status = $remaining < 0 ? 'BREACH' : ($remaining <= $warnMinutes ? 'WARN' : 'OK');
        $updateNext = db()->prepare('UPDATE shipment_nodes SET deadline_utc = ?, remaining_minutes = ?, sla_status = ?, updated_at = datetime("now") WHERE id = ?');
        $updateNext->execute([$deadline->format('Y-m-d H:i:s'), $remaining, $status, $nextNode['id']]);
        record_shipment_event((int) $node['shipment_id'], (int) $nextNode['id'], 'SLA_RECALCULATED', [
            'trigger' => $nodeId,
            'deadline' => $deadline->format('Y-m-d H:i:s'),
            'remaining' => $remaining,
            'status' => $status,
        ]);
    }
}
