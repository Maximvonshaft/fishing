<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';

function authenticate(string $email, string $password): array
{
    $email = mb_strtolower(trim($email));
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $limitConfig = config('security.login_rate_limit');
    $windowMinutes = (int) ($limitConfig['window_minutes'] ?? 5);
    $maxAttempts = (int) ($limitConfig['max_attempts'] ?? 5);
    $lockMinutes = (int) ($limitConfig['lock_minutes'] ?? 10);

    $lockUntil = login_locked_until($email, $ip, $windowMinutes, $maxAttempts, $lockMinutes);
    if ($lockUntil !== null && $lockUntil > new \DateTimeImmutable('now', new \DateTimeZone('UTC'))) {
        $remaining = $lockUntil->getTimestamp() - time();
        $minutes = (int) ceil($remaining / 60);
        throw new \RuntimeException('账户已锁定，请 ' . $minutes . ' 分钟后再试');
    }

    $stmt = db()->prepare('SELECT u.*, v.active AS vendor_active FROM users u LEFT JOIN vendors v ON v.id = u.vendor_id WHERE LOWER(u.email) = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    $success = false;
    if ($user && (int) $user['active'] === 1) {
        if ($user['role'] === 'vendor' && isset($user['vendor_active']) && (int) $user['vendor_active'] !== 1) {
            $user = false;
        }
    } else {
        $user = false;
    }

    if ($user && password_verify($password, $user['password_hash'])) {
        $success = true;
    }

    record_login_attempt($email, $ip, $success);

    if (!$success || !$user) {
        throw new \RuntimeException('邮箱或密码错误');
    }

    if (password_needs_rehash($user['password_hash'], defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT)) {
        $newHash = password_hash_secure($password);
        $upd = db()->prepare('UPDATE users SET password_hash = ?, updated_at = datetime("now") WHERE id = ?');
        $upd->execute([$newHash, $user['id']]);
    }

    $updateLogin = db()->prepare('UPDATE users SET last_login_at = datetime("now"), updated_at = datetime("now") WHERE id = ?');
    $updateLogin->execute([$user['id']]);

    $_SESSION['user_id'] = $user['id'];
    session_regenerate_id(true);

    add_audit_event($user['id'], $user['id'], 'LOGIN_SUCCEEDED', []);

    return $user;
}

function login_locked_until(string $email, string $ip, int $windowMinutes, int $maxAttempts, int $lockMinutes): ?\DateTimeImmutable
{
    $threshold = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('-' . $windowMinutes . ' minutes');
    $stmt = db()->prepare('SELECT MAX(created_at) AS last_failure, COUNT(*) AS failures FROM login_attempts WHERE created_at >= ? AND successful = 0 AND (LOWER(email) = ? OR ip = ?)');
    $stmt->execute([$threshold->format('Y-m-d H:i:s'), mb_strtolower($email), $ip]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    if ((int) $row['failures'] < $maxAttempts) {
        return null;
    }

    if (empty($row['last_failure'])) {
        return null;
    }

    $lastFailure = new \DateTimeImmutable($row['last_failure'], new \DateTimeZone('UTC'));
    return $lastFailure->modify('+' . $lockMinutes . ' minutes');
}

function record_login_attempt(string $email, string $ip, bool $success): void
{
    $stmt = db()->prepare('INSERT INTO login_attempts (email, ip, successful, created_at) VALUES (?, ?, ?, datetime("now"))');
    $stmt->execute([mb_strtolower($email), $ip, $success ? 1 : 0]);
    if (!$success) {
        add_audit_event(null, null, 'LOGIN_FAILED', ['email' => $email, 'ip' => $ip]);
    }
}

function logout_user(): void
{
    $user = current_user();
    if ($user) {
        add_audit_event($user['id'], $user['id'], 'LOGOUT', []);
    }

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function ensure_force_password_change(): void
{
    $user = current_user();
    if (!$user) {
        return;
    }

    if ((int) $user['force_password_reset'] === 1 && ($_GET['page'] ?? '') !== 'first-setup') {
        redirect(route('auth.first_setup'));
    }
}

