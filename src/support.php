<?php

declare(strict_types=1);

use App\Database;

function db(): \PDO
{
    return Database::connection();
}

function config(string $key, mixed $default = null): mixed
{
    static $config;
    if ($config === null) {
        $config = require __DIR__ . '/../config/env.php';
    }

    $segments = explode('.', $key);
    $value = $config;
    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

function csrf_token(): string
{
    $key = config('security.csrf_token_key', '_csrf_token');
    return $_SESSION[$key] ?? '';
}

function verify_csrf_token(?string $token): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        return;
    }

    $expected = csrf_token();
    if (!is_string($token) || !hash_equals($expected, $token)) {
        http_response_code(403);
        throw new \RuntimeException('Invalid CSRF token.');
    }
}

function view(string $template, array $data = []): void
{
    $layout = $data['_layout'] ?? 'layouts.app';
    unset($data['_layout']);

    extract($data, EXTR_SKIP);
    $viewPath = __DIR__ . '/../resources/views/' . str_replace('.', '/', $template) . '.php';
    if (!file_exists($viewPath)) {
        http_response_code(404);
        echo 'View not found';
        return;
    }

    ob_start();
    include $viewPath;
    $content = ob_get_clean();

    $layoutPath = __DIR__ . '/../resources/views/' . str_replace('.', '/', $layout) . '.php';
    if (!file_exists($layoutPath)) {
        echo $content;
        return;
    }

    include $layoutPath;
}

function app_script_path(): string
{
    static $script;
    if ($script !== null) {
        return $script;
    }

    $configured = config('app.base_script');
    if (is_string($configured) && $configured !== '') {
        $script = $configured;
        return $script;
    }

    $scriptName = $_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? '');

    if ($scriptName === '') {
        $requestPath = $_SERVER['REQUEST_URI'] ?? '';
        if ($requestPath !== '') {
            $path = parse_url($requestPath, PHP_URL_PATH);
            if (is_string($path) && $path !== '') {
                if (!str_contains($path, '.php')) {
                    $path = rtrim($path, '/');
                    $path = ($path === '' ? '' : $path) . '/index.php';
                    if ($path[0] !== '/') {
                        $path = '/' . $path;
                    }
                }
                $scriptName = $path;
            }
        }
    }

    if ($scriptName === '') {
        $scriptFile = $_SERVER['SCRIPT_FILENAME'] ?? null;
        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? null;
        if (is_string($scriptFile) && is_string($documentRoot)) {
            $realScript = realpath($scriptFile) ?: $scriptFile;
            $realRoot = realpath($documentRoot) ?: $documentRoot;
            $normalizedScript = str_replace(chr(92), '/', $realScript);
            $normalizedRoot = str_replace(chr(92), '/', $realRoot);
            $normalizedRoot = rtrim($normalizedRoot, '/');
            if ($normalizedRoot === '') {
                $normalizedRoot = '/';
            }
            if (str_starts_with($normalizedScript, $normalizedRoot)) {
                $scriptName = substr($normalizedScript, strlen($normalizedRoot));
            }
        }
    }

    if ($scriptName === '') {
        $scriptName = '/index.php';
    }

    if ($scriptName[0] !== '/') {
        $scriptName = '/' . ltrim($scriptName, '/');
    }

    $script = $scriptName;

    return $script;
}

function route(string $name, array $params = []): string
{
    $map = [
        'projects.index' => 'projects',
        'shipments.index' => 'shipments',
        'shipments.show' => 'shipment-detail',
        'tasks.index' => 'tasks',
        'templates.index' => 'templates',
        'reports.index' => 'reports',
        'settings.index' => 'settings',
        'auth.login' => 'login',
        'auth.first_setup' => 'first-setup',
        'users.manage' => 'users',
        'vendors.manage' => 'vendors',
        'tasks.show' => 'node',
        'auth.logout' => ['action' => 'logout'],
    ];

    $target = $map[$name] ?? 'projects';
    if (is_array($target)) {
        $queryParams = array_merge($target, $params);
    } else {
        $queryParams = array_merge(['page' => $target], $params);
    }

    $query = http_build_query($queryParams);
    $script = app_script_path();

    return $query === '' ? $script : $script . '?' . $query;
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function json_response(mixed $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
}

function error_response(string $code, string $message, array $details = [], int $status = 400): void
{
    json_response(['error' => ['code' => $code, 'message' => $message, 'details' => $details]], $status);
}

function format_datetime(?string $datetime, ?string $tz = null): ?string
{
    if ($datetime === null) {
        return null;
    }

    $tz = $tz ?: config('app.display_timezone', 'Europe/Tirane');
    $dt = new \DateTimeImmutable($datetime, new \DateTimeZone('UTC'));
    return $dt->setTimezone(new \DateTimeZone($tz))->format('Y-m-d H:i');
}

function current_user(): ?array
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    static $user;
    if ($user !== null) {
        return $user;
    }

    $stmt = db()->prepare('SELECT u.*, v.name AS vendor_name FROM users u LEFT JOIN vendors v ON v.id = u.vendor_id WHERE u.id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;

    if (!$user || (int) $user['active'] !== 1) {
        unset($_SESSION['user_id']);
        $user = null;
    }

    return $user;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        redirect(route('auth.login'));
    }

    return $user;
}

function require_role(array $roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }

    return $user;
}

function flash(string $key, ?string $value = null): ?string
{
    if ($value === null) {
        if (!isset($_SESSION['_flash'][$key])) {
            return null;
        }
        $val = $_SESSION['_flash'][$key];
        unset($_SESSION['_flash'][$key]);
        return $val;
    }

    $_SESSION['_flash'][$key] = $value;
    return null;
}

function add_audit_event(?int $actorId, ?int $targetId, string $action, array $payload = []): void
{
    try {
        $stmt = db()->prepare('INSERT INTO audit_events (actor_user_id, target_user_id, action, ip, user_agent, payload_json, created_at) VALUES (?, ?, ?, ?, ?, ?, datetime("now"))');
        $stmt->execute([
            $actorId,
            $targetId,
            $action,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
        ]);
    } catch (\PDOException $e) {
        error_log('[audit] Unable to persist audit event: ' . $e->getMessage());
    } catch (\Throwable $e) {
        error_log('[audit] Unexpected failure while writing audit event: ' . $e->getMessage());
    }
}

function password_hash_secure(string $password): string
{
    if (defined('PASSWORD_ARGON2ID')) {
        return password_hash($password, PASSWORD_ARGON2ID);
    }

    return password_hash($password, PASSWORD_BCRYPT);
}

function random_password(int $length = 14): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*';
    $result = '';
    $max = strlen($alphabet) - 1;
    for ($i = 0; $i < $length; $i++) {
        $result .= $alphabet[random_int(0, $max)];
    }
    return $result;
}

