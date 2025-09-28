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
    private static bool $schemaChecked = false;

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
        self::runSelfChecks($pdo);

        return self::$pdo;
    }

    private static function runSelfChecks(PDO $pdo): void
    {
        if (self::$schemaChecked) {
            return;
        }

        self::$schemaChecked = true;

        try {
            $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'audit_events' LIMIT 1");
            $exists = $stmt !== false && $stmt->fetchColumn() !== false;
            if (!$exists) {
                error_log('[bootstrap] Required table audit_events is missing. Run database migrations.');
            }
        } catch (Throwable $e) {
            error_log('[bootstrap] Failed to verify audit_events table: ' . $e->getMessage());
        }
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
            // countries & templates
            "CREATE TABLE IF NOT EXISTS countries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                updated_at TEXT NOT NULL DEFAULT (datetime('now'))
            )",
            "CREATE TABLE IF NOT EXISTS country_nodes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                country_id INTEGER NOT NULL,
                sort_order INTEGER NOT NULL,
                name TEXT NOT NULL,
                vendor_id INTEGER NOT NULL,
                default_assignee_user_id INTEGER NULL,
                base_type TEXT NOT NULL,
                sla_hours REAL NOT NULL DEFAULT 0,
                evidence_required INTEGER NOT NULL DEFAULT 0,
                signature_required INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                updated_at TEXT NOT NULL DEFAULT (datetime('now')),
                UNIQUE(country_id, sort_order),
                FOREIGN KEY(country_id) REFERENCES countries(id) ON DELETE CASCADE,
                FOREIGN KEY(vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
                FOREIGN KEY(default_assignee_user_id) REFERENCES users(id) ON DELETE SET NULL
            )",
            "CREATE INDEX IF NOT EXISTS idx_country_nodes_vendor ON country_nodes(vendor_id)",
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
                country_id INTEGER NOT NULL,
                code TEXT NOT NULL,
                origin TEXT,
                eta_dest_airport TEXT NOT NULL,
                meta_json TEXT,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                updated_at TEXT NOT NULL DEFAULT (datetime('now')),
                UNIQUE(country_id, code),
                FOREIGN KEY(country_id) REFERENCES countries(id) ON DELETE CASCADE
            )",
            // shipment nodes (milestones)
            "CREATE TABLE IF NOT EXISTS shipment_nodes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                shipment_id INTEGER NOT NULL,
                country_node_id INTEGER NULL,
                name TEXT NOT NULL,
                sort_order INTEGER NOT NULL,
                vendor_id INTEGER NOT NULL,
                assignee_user_id INTEGER NULL,
                base_type TEXT NOT NULL,
                sla_hours REAL NOT NULL DEFAULT 0,
                evidence_required INTEGER NOT NULL DEFAULT 0,
                signature_required INTEGER NOT NULL DEFAULT 0,
                status TEXT NOT NULL DEFAULT 'PENDING',
                deadline_utc TEXT,
                remaining_minutes INTEGER,
                sla_status TEXT NOT NULL DEFAULT 'NA',
                actual_time TEXT,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                updated_at TEXT NOT NULL DEFAULT (datetime('now')),
                FOREIGN KEY(shipment_id) REFERENCES shipments(id) ON DELETE CASCADE,
                FOREIGN KEY(country_node_id) REFERENCES country_nodes(id) ON DELETE SET NULL,
                FOREIGN KEY(vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
                FOREIGN KEY(assignee_user_id) REFERENCES users(id) ON DELETE SET NULL,
                UNIQUE(shipment_id, sort_order)
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
                image_path TEXT,
                image_sha256 TEXT,
                image_bytes INTEGER,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                FOREIGN KEY(node_id) REFERENCES shipment_nodes(id) ON DELETE CASCADE,
                FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
            )",
            // shipment events
            "CREATE TABLE IF NOT EXISTS shipment_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                shipment_id INTEGER NOT NULL,
                node_id INTEGER NULL,
                event_type TEXT NOT NULL,
                payload_json TEXT,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                FOREIGN KEY(shipment_id) REFERENCES shipments(id) ON DELETE CASCADE,
                FOREIGN KEY(node_id) REFERENCES shipment_nodes(id) ON DELETE SET NULL
            )",
            "CREATE INDEX IF NOT EXISTS idx_shipment_events_shipment ON shipment_events(shipment_id, created_at)"
        ];

        foreach ($queries as $sql) {
            $pdo->exec($sql);
        }

        self::ensureColumns($pdo);
    }

    private static function ensureColumns(PDO $pdo): void
    {
        $columns = static fn(string $table): array => $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC);

        $hasCountryId = false;
        foreach ($columns('shipments') as $col) {
            if ($col['name'] === 'country_id') {
                $hasCountryId = true;
            }
        }
        if (!$hasCountryId) {
            $pdo->exec("ALTER TABLE shipments ADD COLUMN country_id INTEGER NULL REFERENCES countries(id) ON DELETE CASCADE");
            $pdo->exec("UPDATE shipments SET country_id = NULL");
        }

        $hasMeta = false;
        foreach ($columns('shipments') as $col) {
            if ($col['name'] === 'meta_json') {
                $hasMeta = true;
            }
        }
        if (!$hasMeta) {
            $pdo->exec("ALTER TABLE shipments ADD COLUMN meta_json TEXT NULL");
        }

        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_shipments_country_code ON shipments(country_id, code)");

        $nodeCols = $columns('shipment_nodes');
        $nodeColNames = array_column($nodeCols, 'name');
        if (!in_array('country_node_id', $nodeColNames, true)) {
            $pdo->exec("ALTER TABLE shipment_nodes ADD COLUMN country_node_id INTEGER NULL REFERENCES country_nodes(id) ON DELETE SET NULL");
        }
        if (!in_array('base_type', $nodeColNames, true)) {
            $pdo->exec("ALTER TABLE shipment_nodes ADD COLUMN base_type TEXT NOT NULL DEFAULT 'eta'");
        }
        if (!in_array('sla_hours', $nodeColNames, true)) {
            $pdo->exec("ALTER TABLE shipment_nodes ADD COLUMN sla_hours REAL NOT NULL DEFAULT 0");
        }
        if (!in_array('evidence_required', $nodeColNames, true)) {
            $pdo->exec("ALTER TABLE shipment_nodes ADD COLUMN evidence_required INTEGER NOT NULL DEFAULT 0");
        }
        if (!in_array('signature_required', $nodeColNames, true)) {
            $pdo->exec("ALTER TABLE shipment_nodes ADD COLUMN signature_required INTEGER NOT NULL DEFAULT 0");
        }
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_nodes_shipment_sort_unique ON shipment_nodes(shipment_id, sort_order)");

        $signatureCols = $columns('node_signatures');
        $signatureColNames = array_column($signatureCols, 'name');
        if (!in_array('image_path', $signatureColNames, true)) {
            $pdo->exec("ALTER TABLE node_signatures ADD COLUMN image_path TEXT NULL");
        }
        if (!in_array('image_sha256', $signatureColNames, true)) {
            $pdo->exec("ALTER TABLE node_signatures ADD COLUMN image_sha256 TEXT NULL");
        }
        if (!in_array('image_bytes', $signatureColNames, true)) {
            $pdo->exec("ALTER TABLE node_signatures ADD COLUMN image_bytes INTEGER NULL");
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

            $vendorPassword = password_hash('Vendor#1234', PASSWORD_DEFAULT);
            $pdo->prepare('INSERT INTO users (vendor_id, email, display_name, password_hash, role, active, force_password_reset, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, 0, ?, ?)')
                ->execute([$vendorAId, 'a.operator@example.com', 'Transit Operator', $vendorPassword, 'vendor', $now, $now]);
            $vendorAUserId = (int) $pdo->lastInsertId();

            $pdo->prepare('INSERT INTO users (vendor_id, email, display_name, password_hash, role, active, force_password_reset, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, 0, ?, ?)')
                ->execute([$vendorBId, 'b.operator@example.com', 'Eagle Operator', $vendorPassword, 'vendor', $now, $now]);
            $vendorBUserId = (int) $pdo->lastInsertId();

            // Seed country and template
            $pdo->prepare('INSERT INTO countries (code, name, created_at, updated_at) VALUES (?, ?, ?, ?)')
                ->execute(['ALB', 'Albania', $now, $now]);
            $countryId = (int) $pdo->lastInsertId();

            $insertTemplate = $pdo->prepare('INSERT INTO country_nodes (country_id, sort_order, name, vendor_id, default_assignee_user_id, base_type, sla_hours, evidence_required, signature_required, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $insertTemplate->execute([$countryId, 1, 'NOA Issued', $vendorAId, $vendorAUserId, 'eta', 0, 1, 1, $now, $now]);
            $templateNode1 = (int) $pdo->lastInsertId();
            $insertTemplate->execute([$countryId, 2, 'Customs Clearance Start', $vendorAId, $vendorAUserId, 'previous', 4, 0, 0, $now, $now]);
            $templateNode2 = (int) $pdo->lastInsertId();
            $insertTemplate->execute([$countryId, 3, 'BBL Handover Complete', $vendorBId, $vendorBUserId, 'previous', 12, 1, 1, $now, $now]);
            $templateNode3 = (int) $pdo->lastInsertId();

            // Seed shipments based on template
            $createShipment = $pdo->prepare('INSERT INTO shipments (country_id, code, origin, eta_dest_airport, meta_json, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $shipmentEta1 = gmdate('Y-m-d H:i:s', strtotime('+1 day'));
            $createShipment->execute([$countryId, 'ALB-2024-001', 'PVG', $shipmentEta1, json_encode(['notes' => 'Demo shipment 1']), $now, $now]);
            $shipment1 = (int) $pdo->lastInsertId();

            $shipmentEta2 = gmdate('Y-m-d H:i:s', strtotime('+2 days'));
            $createShipment->execute([$countryId, 'ALB-2024-002', 'TIA', $shipmentEta2, json_encode(['notes' => 'Demo shipment 2']), $now, $now]);
            $shipment2 = (int) $pdo->lastInsertId();

            self::seedShipmentNodes($pdo, $shipment1, $shipmentEta1, $now, [
                ['id' => $templateNode1, 'sort_order' => 1, 'name' => 'NOA Issued', 'vendor_id' => $vendorAId, 'assignee' => $vendorAUserId, 'base_type' => 'eta', 'sla_hours' => 0, 'evidence_required' => 1, 'signature_required' => 1],
                ['id' => $templateNode2, 'sort_order' => 2, 'name' => 'Customs Clearance Start', 'vendor_id' => $vendorAId, 'assignee' => $vendorAUserId, 'base_type' => 'previous', 'sla_hours' => 4, 'evidence_required' => 0, 'signature_required' => 0],
                ['id' => $templateNode3, 'sort_order' => 3, 'name' => 'BBL Handover Complete', 'vendor_id' => $vendorBId, 'assignee' => $vendorBUserId, 'base_type' => 'previous', 'sla_hours' => 12, 'evidence_required' => 1, 'signature_required' => 1],
            ]);

            self::seedShipmentNodes($pdo, $shipment2, $shipmentEta2, $now, [
                ['id' => $templateNode1, 'sort_order' => 1, 'name' => 'NOA Issued', 'vendor_id' => $vendorAId, 'assignee' => $vendorAUserId, 'base_type' => 'eta', 'sla_hours' => 0, 'evidence_required' => 1, 'signature_required' => 1],
                ['id' => $templateNode2, 'sort_order' => 2, 'name' => 'Customs Clearance Start', 'vendor_id' => $vendorAId, 'assignee' => $vendorAUserId, 'base_type' => 'previous', 'sla_hours' => 4, 'evidence_required' => 0, 'signature_required' => 0],
                ['id' => $templateNode3, 'sort_order' => 3, 'name' => 'BBL Handover Complete', 'vendor_id' => $vendorBId, 'assignee' => $vendorBUserId, 'base_type' => 'previous', 'sla_hours' => 12, 'evidence_required' => 1, 'signature_required' => 1],
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private static function seedShipmentNodes(PDO $pdo, int $shipmentId, string $eta, string $now, array $templateNodes): void
    {
        $warnMinutes = (int) (require __DIR__ . '/../config/env.php')['app']['warn_minutes'];
        $insert = $pdo->prepare('INSERT INTO shipment_nodes (shipment_id, country_node_id, name, sort_order, vendor_id, assignee_user_id, base_type, sla_hours, evidence_required, signature_required, status, deadline_utc, remaining_minutes, sla_status, actual_time, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

        foreach ($templateNodes as $node) {
            [$deadline, $remaining, $status] = self::initialSla($node['base_type'], (float) $node['sla_hours'], $eta, $now, $warnMinutes);
            $insert->execute([
                $shipmentId,
                $node['id'],
                $node['name'],
                $node['sort_order'],
                $node['vendor_id'],
                $node['assignee'],
                $node['base_type'],
                $node['sla_hours'],
                $node['evidence_required'],
                $node['signature_required'],
                'PENDING',
                $deadline,
                $remaining,
                $status,
                null,
                $now,
                $now,
            ]);
        }
    }

    private static function initialSla(string $baseType, float $slaHours, string $eta, string $now, int $warnMinutes): array
    {
        $nowDt = new \DateTimeImmutable($now, new \DateTimeZone('UTC'));
        if (in_array($baseType, ['eta', 'creation'], true)) {
            $start = $baseType === 'eta' ? $eta : $now;
            $deadline = (new \DateTimeImmutable($start, new \DateTimeZone('UTC')))->modify('+' . $slaHours . ' hours');
            $remaining = (int) floor(($deadline->getTimestamp() - $nowDt->getTimestamp()) / 60);
            $status = $remaining < 0 ? 'BREACH' : ($remaining <= $warnMinutes ? 'WARN' : 'OK');
            return [$deadline->format('Y-m-d H:i:s'), $remaining, $status];
        }

        return [null, null, 'NA'];
    }
}
