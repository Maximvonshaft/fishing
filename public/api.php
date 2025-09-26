<?php

declare(strict_types=1);


require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/repositories.php';

$path = $_SERVER['PATH_INFO'] ?? ($_GET['r'] ?? '');
$path = trim($path, '/');
$method = $_SERVER['REQUEST_METHOD'];
$payload = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($payload)) {
    error_response('INVALID_JSON', '请求体不是有效的 JSON', [], 400);
    exit;
}

try {
    switch ($path) {
        case 'auth.login':
            handle_api_login($payload);
            break;
        case 'auth.logout':
            require_api_csrf();
            logout_user();
            http_response_code(204);
            break;
        case 'auth.me':
            $user = api_require_login();
            json_response(['user' => format_user_for_api($user)]);
            break;
        case 'countries.list':
            api_require_role(['admin']);
            json_response(list_countries_summary());
            break;
        case 'countries.create':
            api_require_role(['admin']);
            require_api_csrf();
            try {
                $id = create_country($payload['code'] ?? '', $payload['name'] ?? '');
                json_response(['id' => $id], 201);
            } catch (RuntimeException $e) {
                error_response('VALIDATION_ERROR', $e->getMessage());
            }
            break;
        case 'country.nodes':
            api_require_role(['admin']);
            $countryId = isset($_GET['country_id']) ? (int) $_GET['country_id'] : (int) ($payload['country_id'] ?? 0);
            if ($countryId <= 0) {
                error_response('VALIDATION_ERROR', 'country_id 必须提供');
                break;
            }
            $nodes = get_country_nodes($countryId);
            json_response($nodes);
            break;
        case 'country.nodes.save':
            api_require_role(['admin']);
            require_api_csrf();
            $countryId = (int) ($payload['country_id'] ?? 0);
            $nodes = $payload['nodes'] ?? [];
            if (!is_array($nodes)) {
                error_response('VALIDATION_ERROR', 'nodes 格式错误');
                break;
            }
            try {
                save_country_nodes($countryId, $nodes);
                json_response(['status' => 'ok']);
            } catch (RuntimeException $e) {
                error_response('VALIDATION_ERROR', $e->getMessage());
            }
            break;
        case 'vendors.list':
            $user = api_require_role(['admin']);
            $vendors = db()->query('SELECT id, name, active FROM vendors ORDER BY name')->fetchAll();
            json_response($vendors);
            break;
        case 'vendors.create':
            $user = api_require_role(['admin']);
            require_api_csrf();
            $name = trim((string)($payload['name'] ?? ''));
            if ($name === '') {
                error_response('VALIDATION_ERROR', '供应商名称不能为空', ['field' => 'name']);
                break;
            }
            $stmt = db()->prepare('INSERT INTO vendors (name, contact_email, contact_phone, active, created_at, updated_at) VALUES (?, ?, ?, 1, datetime("now"), datetime("now"))');
            $stmt->execute([$name, $payload['contact_email'] ?? null, $payload['contact_phone'] ?? null]);
            $id = (int) db()->lastInsertId();
            add_audit_event($user['id'], null, 'VENDOR_CREATED', ['vendor_id' => $id]);
            json_response(['id' => $id], 201);
            break;
        case 'vendors.update':
            $user = api_require_role(['admin']);
            require_api_csrf();
            $id = (int) ($payload['id'] ?? 0);
            $stmt = db()->prepare('UPDATE vendors SET contact_email = ?, contact_phone = ?, updated_at = datetime("now") WHERE id = ?');
            $stmt->execute([$payload['contact_email'] ?? null, $payload['contact_phone'] ?? null, $id]);
            add_audit_event($user['id'], null, 'VENDOR_UPDATED', ['vendor_id' => $id]);
            json_response(['status' => 'ok']);
            break;
        case 'vendors.toggle':
            $user = api_require_role(['admin']);
            require_api_csrf();
            $id = (int) ($payload['id'] ?? 0);
            $active = (int) ($payload['active'] ?? 0);
            if ($active === 0) {
                $stmt = db()->prepare('SELECT COUNT(*) FROM users WHERE vendor_id = ? AND active = 1');
                $stmt->execute([$id]);
                if ((int) $stmt->fetchColumn() > 0) {
                    error_response('STATE_CONFLICT', '停用前请先停用或转移该供应商下账号');
                    break;
                }
            }
            $stmt = db()->prepare('UPDATE vendors SET active = ?, updated_at = datetime("now") WHERE id = ?');
            $stmt->execute([$active, $id]);
            add_audit_event($user['id'], null, 'VENDOR_TOGGLED', ['vendor_id' => $id, 'active' => $active]);
            json_response(['status' => 'ok']);
            break;
        case 'users.list':
            $user = api_require_role(['admin']);
            $stmt = db()->prepare('SELECT u.id, u.email, u.display_name, u.role, u.active, u.last_login_at, v.name AS vendor_name FROM users u LEFT JOIN vendors v ON v.id = u.vendor_id ORDER BY u.created_at DESC');
            $stmt->execute();
            json_response($stmt->fetchAll());
            break;
        case 'users.create':
            $user = api_require_role(['admin']);
            require_api_csrf();
            $email = trim((string)($payload['email'] ?? ''));
            $displayName = trim((string)($payload['display_name'] ?? ''));
            $role = $payload['role'] ?? 'vendor';
            $vendorId = $payload['vendor_id'] ?? null;
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                error_response('VALIDATION_ERROR', '邮箱格式错误', ['field' => 'email']);
                break;
            }
            if ($displayName === '') {
                error_response('VALIDATION_ERROR', '显示名称不能为空', ['field' => 'display_name']);
                break;
            }
            if ($role === 'vendor') {
                if (!$vendorId) {
                    error_response('VALIDATION_ERROR', '供应商账号必须指定供应商', ['field' => 'vendor_id']);
                    break;
                }
                $vendorStmt = db()->prepare('SELECT id, active FROM vendors WHERE id = ?');
                $vendorStmt->execute([$vendorId]);
                $vendor = $vendorStmt->fetch();
                if (!$vendor || (int) $vendor['active'] !== 1) {
                    error_response('VALIDATION_ERROR', '供应商不存在或已停用', ['field' => 'vendor_id']);
                    break;
                }
            } else {
                $vendorId = null;
            }
            $tempPassword = $payload['temp_password'] ?? random_password();
            $hash = password_hash_secure($tempPassword);
            $stmt = db()->prepare('INSERT INTO users (vendor_id, email, display_name, password_hash, role, active, force_password_reset, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, 1, datetime("now"), datetime("now"))');
            $stmt->execute([$vendorId, mb_strtolower($email), $displayName, $hash, $role]);
            $newId = (int) db()->lastInsertId();
            add_audit_event($user['id'], $newId, 'USER_CREATED', ['email' => $email]);
            json_response(['id' => $newId, 'temp_password' => $tempPassword], 201);
            break;
        case 'users.reset_password':
            $user = api_require_role(['admin']);
            require_api_csrf();
            $targetId = (int) ($payload['user_id'] ?? 0);
            $temp = random_password();
            $hash = password_hash_secure($temp);
            $stmt = db()->prepare('UPDATE users SET password_hash = ?, force_password_reset = 1, updated_at = datetime("now") WHERE id = ?');
            $stmt->execute([$hash, $targetId]);
            add_audit_event($user['id'], $targetId, 'PASSWORD_RESET_ISSUED', []);
            json_response(['temp_password' => $temp]);
            break;
        case 'users.toggle':
            $user = api_require_role(['admin']);
            require_api_csrf();
            $targetId = (int) ($payload['user_id'] ?? 0);
            $active = (int) ($payload['active'] ?? 0);
            $stmt = db()->prepare('UPDATE users SET active = ?, updated_at = datetime("now") WHERE id = ?');
            $stmt->execute([$active, $targetId]);
            add_audit_event($user['id'], $targetId, 'USER_TOGGLED', ['active' => $active]);
            json_response(['status' => 'ok']);
            break;
        case 'shipments.create':
            $user = api_require_role(['admin']);
            require_api_csrf();
            try {
                $shipmentId = create_shipment($payload, $user['id']);
                json_response(['id' => $shipmentId], 201);
            } catch (RuntimeException $e) {
                error_response('VALIDATION_ERROR', $e->getMessage());
            }
            break;
        case 'shipments.list':
            api_require_role(['admin']);
            $filters = [
                'status' => $_GET['status'] ?? null,
                'q' => $_GET['q'] ?? null,
            ];
            $countryCode = $_GET['country'] ?? null;
            $shipments = get_shipments($countryCode, $filters);
            json_response($shipments);
            break;
        case 'shipments.show':
            $user = api_require_login();
            $shipmentId = isset($_GET['id']) ? (int) $_GET['id'] : (int) ($payload['id'] ?? 0);
            $detail = get_shipment_detail($shipmentId, $user);
            if (!$detail) {
                error_response('NOT_FOUND', '批次不存在', [], 404);
                break;
            }
            json_response($detail);
            break;
        case 'users.update_profile':
            $user = api_require_login();
            require_api_csrf();
            $display = trim((string)($payload['display_name'] ?? ''));
            if ($display !== '') {
                $stmt = db()->prepare('UPDATE users SET display_name = ?, updated_at = datetime("now") WHERE id = ?');
                $stmt->execute([$display, $user['id']]);
            }
            if (!empty($payload['new_password'])) {
                if (strlen((string) $payload['new_password']) < 8) {
                    error_response('VALIDATION_ERROR', '新密码至少 8 位');
                    break;
                }
                $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
                $stmt->execute([$user['id']]);
                $row = $stmt->fetch();
                if (!$row || !password_verify((string) ($payload['current_password'] ?? ''), $row['password_hash'])) {
                    error_response('FORBIDDEN', '当前密码错误', [], 403);
                    break;
                }
                $hash = password_hash_secure((string) $payload['new_password']);
                $update = db()->prepare('UPDATE users SET password_hash = ?, force_password_reset = 0, password_changed_at = datetime("now"), updated_at = datetime("now") WHERE id = ?');
                $update->execute([$hash, $user['id']]);
                add_audit_event($user['id'], $user['id'], 'PASSWORD_CHANGED', []);
            }
            json_response(['status' => 'ok']);
            break;
        case 'todos.list':
            $user = api_require_login();
            if ($user['role'] === 'admin' && isset($payload['vendor_id'])) {
                $tasks = fetch_user_tasks_for_vendor((int) $payload['vendor_id']);
            } else {
                $tasks = fetch_user_tasks($user);
            }
            json_response($tasks);
            break;
        default:
            error_response('NOT_FOUND', '未知接口', [], 404);
            break;
    }
} catch (RuntimeException $e) {
    error_response('ERROR', $e->getMessage(), [], 400);
}

function require_api_csrf(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    try {
        verify_csrf_token($token);
    } catch (RuntimeException $e) {
        error_response('FORBIDDEN', 'CSRF 校验失败', [], 403);
        exit;
    }
}

function handle_api_login(array $payload): void
{
    try {
        $user = authenticate((string) ($payload['email'] ?? ''), (string) ($payload['password'] ?? ''));
        json_response(['user' => format_user_for_api($user)]);
    } catch (RuntimeException $e) {
        error_response('UNAUTHORIZED', $e->getMessage(), [], 401);
    }
}

function format_user_for_api(array $user): array
{
    return [
        'id' => (int) $user['id'],
        'role' => $user['role'],
        'vendor_id' => $user['vendor_id'],
        'display_name' => $user['display_name'],
    ];
}

function api_require_login(): array
{
    $user = current_user();
    if (!$user) {
        error_response('UNAUTHORIZED', '未登录或会话失效', [], 401);
        exit;
    }

    if ((int) $user['active'] !== 1) {
        error_response('UNAUTHORIZED', '账号已停用', [], 401);
        exit;
    }

    return $user;
}

function api_require_role(array $roles): array
{
    $user = api_require_login();
    if (!in_array($user['role'], $roles, true)) {
        error_response('FORBIDDEN', '无权访问该接口', [], 403);
        exit;
    }

    return $user;
}
