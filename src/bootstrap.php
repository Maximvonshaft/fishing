<?php

declare(strict_types=1);

use App\Database;

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/support.php';

$config = require __DIR__ . '/../config/env.php';

if (!headers_sent()) {
    $sessionName = $config['security']['session_name'] ?? 'fishing_session';
    session_name($sessionName);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start([
        'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
        'cookie_lifetime' => 0,
    ]);
}

date_default_timezone_set($config['app']['timezone'] ?? 'UTC');

Database::connection();

if (!isset($_SESSION[$config['security']['csrf_token_key']])) {
    $_SESSION[$config['security']['csrf_token_key']] = bin2hex(random_bytes(32));
}
