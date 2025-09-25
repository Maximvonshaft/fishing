<?php

declare(strict_types=1);

namespace App;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Simple PDO helper for SQLite with required pragmas.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $config = require __DIR__ . '/../config/env.php';
        $path = $config['db']['path'] ?? null;

        if (!is_string($path) || $path === '') {
            throw new RuntimeException('Database path is not configured.');
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new RuntimeException('Unable to create database directory.');
            }
        }

        $needInit = !file_exists($path);

        $dsn = 'sqlite:' . $path;
        $pdo = new PDO($dsn);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        $pdo->exec("PRAGMA foreign_keys = ON");
        $pdo->exec("PRAGMA journal_mode = WAL");
        $pdo->exec("PRAGMA synchronous = NORMAL");
        $pdo->exec("PRAGMA busy_timeout = 5000");

        if ($needInit) {
            touch($path);
        }

        self::$pdo = $pdo;
        Migrations::run($pdo);
        Seeders::run($pdo);

        return self::$pdo;
    }
}

final class Migrations
{
    public static function run(PDO $pdo): void
    {
        $queries = [
            // vendors
            "CREATE TABLE IF NOT EXISTS vendors (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                contact_email TEXT,
                contact_phone TEXT,
                active INTEGER NOT NULL DEFAULT 1,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                updated_at TEXT NOT NULL DEFAULT (datetime('now'))
            )",
            // users
            "CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                vendor_id INTEGER NULL,
                email TEXT NOT NULL UNIQUE,
                display_name TEXT NOT NULL,
                password_hash TEXT NOT NULL,
                role TEXT NOT NULL,
                active INTEGER NOT NULL DEFAULT 1,
                force_password_reset INTEGER NOT NULL DEFAULT 0,
                last_login_at TEXT NULL,
                password_changed_at TEXT NULL,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                updated_at TEXT NOT NULL DEFAULT (datetime('now')),
                FOREIGN KEY(vendor_id) REFERENCES vendors(id) ON DELETE SET NULL
            )",
            "CREATE INDEX IF NOT EXISTS idx_users_vendor_id ON users(vendor_id)",
            // login attempts
            "CREATE TABLE IF NOT EXISTS login_attempts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL,
                ip TEXT NOT NULL,
                successful INTEGER NOT NULL,
                created_at TEXT NOT NULL DEFAULT (datetime('now'))
            )",
            "CREATE INDEX IF NOT EXISTS idx_login_attempts_email_created ON login_attempts(email, created_at)",
            "CREATE INDEX IF NOT EXISTS idx_login_attempts_ip_created ON login_attempts(ip, created_at)",
            // password reset tokens
            "CREATE TABLE IF NOT EXISTS password_resets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                token TEXT NOT NULL,
                expires_at TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
            )",
            "CREATE UNIQUE INDEX IF NOT EXISTS idx_password_resets_token ON password_resets(token)",
            // events audit log
            "CREATE TABLE IF NOT EXISTS audit_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                actor_user_id INTEGER NULL,
                target_user_id INTEGER NULL,
                action TEXT NOT NULL,
                ip TEXT,
                user_agent TEXT,
                payload_json TEXT,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                FOREIGN KEY(actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY(target_user_id) REFERENCES users(id) ON DELETE SET NULL
            )",
            "CREATE INDEX IF NOT EXISTS idx_audit_events_target_created ON audit_events(target_user_id, created_at)",
            // shipments
            "CREATE TABLE IF NOT EXISTS shipments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                country TEXT NOT NULL,
                code TEXT NOT NULL,
                origin TEXT,
                eta_dest_airport TEXT,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                updated_at TEXT NOT NULL DEFAULT (datetime('now')),
                UNIQUE(country, code)
            )",
            // shipment nodes (milestones)
            "CREATE TABLE IF NOT EXISTS shipment_nodes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                shipment_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                sort_order INTEGER NOT NULL,
                vendor_id INTEGER NOT NULL,
                assignee_user_id INTEGER NULL,
                status TEXT NOT NULL DEFAULT 'PENDING',
                deadline_utc TEXT,
                remaining_minutes INTEGER,
                sla_status TEXT NOT NULL DEFAULT 'NA',
                actual_time TEXT,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                updated_at TEXT NOT NULL DEFAULT (datetime('now')),
                FOREIGN KEY(shipment_id) REFERENCES shipments(id) ON DELETE CASCADE,
                FOREIGN KEY(vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
                FOREIGN KEY(assignee_user_id) REFERENCES users(id) ON DELETE SET NULL
            )",
            "CREATE INDEX IF NOT EXISTS idx_nodes_shipment_sort ON shipment_nodes(shipment_id, sort_order)",
            "CREATE INDEX IF NOT EXISTS idx_nodes_vendor_status ON shipment_nodes(vendor_id, status)",
            "CREATE INDEX IF NOT EXISTS idx_nodes_assignee_status ON shipment_nodes(assignee_user_id, status)",
            // attachments metadata placeholder
            "CREATE TABLE IF NOT EXISTS node_files (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                node_id INTEGER NOT NULL,
                file_name TEXT NOT NULL,
                mime_type TEXT NOT NULL,
                size_bytes INTEGER NOT NULL,
                sha256 TEXT NOT NULL,
                storage_path TEXT NOT NULL,
                uploaded_by INTEGER NOT NULL,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                FOREIGN KEY(node_id) REFERENCES shipment_nodes(id) ON DELETE CASCADE,
                FOREIGN KEY(uploaded_by) REFERENCES users(id) ON DELETE CASCADE
            )",
            // signatures placeholder
            "CREATE TABLE IF NOT EXISTS node_signatures (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                node_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                method TEXT NOT NULL,
                signer_name TEXT NOT NULL,
                ip TEXT,
                device TEXT,
                geo_lat REAL,
                geo_lng REAL,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                FOREIGN KEY(node_id) REFERENCES shipment_nodes(id) ON DELETE CASCADE,
                FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
            )"
        ];

        foreach ($queries as $sql) {
            $pdo->exec($sql);
        }
    }
}

final class Seeders
{
    public static function run(PDO $pdo): void
    {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $now = gmdate('Y-m-d H:i:s');

        $pdo->beginTransaction();
        try {
            $pdo->prepare('INSERT INTO vendors (name, contact_email, active, created_at, updated_at) VALUES (?, ?, 1, ?, ?)')
                ->execute(['Transit Albania', 'ops@transit-al.com', $now, $now]);
            $vendorAId = (int) $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO vendors (name, contact_email, active, created_at, updated_at) VALUES (?, ?, 1, ?, ?)')
                ->execute(['Eagle Logistics', 'support@eagle-log.com', $now, $now]);
            $vendorBId = (int) $pdo->lastInsertId();

            $adminPassword = password_hash('Admin#1234', PASSWORD_DEFAULT);
            $pdo->prepare('INSERT INTO users (vendor_id, email, display_name, password_hash, role, active, force_password_reset, created_at, updated_at) VALUES (NULL, ?, ?, ?, ?, 1, 1, ?, ?)')
                ->execute(['admin@example.com', 'System Admin', $adminPassword, 'admin', $now, $now]);
            $adminId = (int) $pdo->lastInsertId();

            $vendorPassword = password_hash('Vendor#1234', PASSWORD_DEFAULT);
            $pdo->prepare('INSERT INTO users (vendor_id, email, display_name, password_hash, role, active, force_password_reset, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, 0, ?, ?)')
                ->execute([$vendorAId, 'a.operator@example.com', 'Transit Operator', $vendorPassword, 'vendor', $now, $now]);
            $vendorAUserId = (int) $pdo->lastInsertId();

            $pdo->prepare('INSERT INTO users (vendor_id, email, display_name, password_hash, role, active, force_password_reset, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, 0, ?, ?)')
                ->execute([$vendorBId, 'b.operator@example.com', 'Eagle Operator', $vendorPassword, 'vendor', $now, $now]);
            $vendorBUserId = (int) $pdo->lastInsertId();

            // Seed shipments and nodes
            $pdo->prepare('INSERT INTO shipments (country, code, origin, eta_dest_airport, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute(['ALB', 'ALB-2024-001', 'PVG', gmdate('Y-m-d H:i:s', strtotime('+1 day')), $now, $now]);
            $shipment1 = (int) $pdo->lastInsertId();

            $pdo->prepare('INSERT INTO shipments (country, code, origin, eta_dest_airport, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute(['ALB', 'ALB-2024-002', 'TIA', gmdate('Y-m-d H:i:s', strtotime('+2 days')), $now, $now]);
            $shipment2 = (int) $pdo->lastInsertId();

            $insertNode = $pdo->prepare('INSERT INTO shipment_nodes (shipment_id, name, sort_order, vendor_id, assignee_user_id, status, deadline_utc, remaining_minutes, sla_status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

            $deadline1 = gmdate('Y-m-d H:i:s', strtotime('+6 hours'));
            $insertNode->execute([$shipment1, 'NOA Issued', 1, $vendorAId, $vendorAUserId, 'PENDING', $deadline1, 360, 'OK', $now, $now]);
            $insertNode->execute([$shipment1, 'BBL Handover', 2, $vendorAId, null, 'PENDING', gmdate('Y-m-d H:i:s', strtotime('+12 hours')), 720, 'OK', $now, $now]);
            $insertNode->execute([$shipment2, 'Customs Inspection', 1, $vendorBId, $vendorBUserId, 'PENDING', gmdate('Y-m-d H:i:s', strtotime('+8 hours')), 480, 'WARN', $now, $now]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
