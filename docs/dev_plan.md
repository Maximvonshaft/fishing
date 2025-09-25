# PHP Prototype End-to-End Implementation Plan

This document captures a pragmatic plan to turn the static Blade-like prototype
into a minimal but production-ready logistics console without adopting
frameworks such as Laravel. The scope focuses on "country = template, shipment
= execution" with nodes defined by a name, owning vendor, base start point, and
SLA hours. Evidence uploads and signatures are optional but accommodated for
future expansion.

## 0. Guiding Principles

* Preserve the current public entry point (`public/index.php`) and Blade-style
  views under `resources/views`.
* Introduce a lightweight API façade (`public/api.php`) and back it with a
  relational database accessed through PDO.
* Keep the SLA engine simple: three base start modes (`previous`, `eta`,
  `creation`) and duration in hours. Compute status colours (OK/WARN/BREACH)
  using a single warning threshold.
* Implement minimal RBAC for administrators, internal PMs, and vendor users,
  with write access limited to assigned nodes.
* Uploads and signatures are optional toggles. Default behaviour ships with
  them disabled but the data model anticipates the features.

## 1. Runtime Layout

```
/public
  ├── index.php       # existing view router
  ├── api.php         # new API entry point
  ├── assets/
/config
  └── env.php         # runtime configuration
/src
  ├── db.php          # PDO wrapper
  ├── auth.php        # sessions and permission helpers
  ├── router.php      # API route dispatcher
  ├── services/
  │     ├── Sla.php
  │     ├── Files.php
  │     └── Events.php
  ├── repos/          # data mappers
  └── uploads/
/migrations
  └── 001_init.sql
/docs
  └── api_spec.md
```

### Configuration (`config/env.php`)

```php
return [
  'db' => [
    'dsn'  => 'mysql:host=127.0.0.1;dbname=ops;charset=utf8mb4',
    'user' => 'ops',
    'pass' => 'secret',
  ],
  'app' => [
    'timezone'    => 'UTC',
    'display_tz'  => 'Europe/Tirane',
    'warn_minutes'=> 120,
  ],
  'upload' => [
    'dir'      => __DIR__ . '/../storage/uploads',
    'max_size' => 20 * 1024 * 1024,
    'allow'    => ['pdf', 'jpg', 'jpeg', 'png'],
  ],
];
```

## 2. Data Model

### Template Layer

* `countries`
* `vendors`
* `users` (`internal_admin`, `pm`, `vendor`, `approver`, `auditor`)
* `country_nodes`
  * `name`, `vendor_id`, `base_type`, `sla_hours`, `evidence_required`,
    `signature_required`

### Execution Layer

* `shipments`
* `shipment_nodes`
  * Snapshot of template fields plus execution data (`actual_time`,
    `deadline_utc`, `status`, `sla_status`, `remaining_minutes`)

### Optional Artifacts

* `files`, `evidences`, `signatures`, `events`

A starter migration (`migrations/001_init.sql`) creates the core tables and
indices required for performant queries.

## 3. Security & Permissions

* PHP sessions with secure cookies (`Secure`, `HttpOnly`, `SameSite=Lax`).
* Password hashing via `password_hash` / `password_verify`; throttle repeated
  failures per IP and username.
* RBAC rules: internal users can configure templates and manage shipments;
  vendors can only complete nodes assigned to their vendor.
* CSRF tokens for POST forms, AJAX header `X-CSRF-Token`.
* Upload allow-list by MIME and extension, plus `sha256` fingerprinting.

## 4. SLA Engine

* Base start options:
  * `previous` – previous node actual completion time.
  * `eta` – shipment ETA at destination airport.
  * `creation` – shipment creation timestamp.
* If the base start is missing, mark SLA as `NA`.
* Compute deadline = start + `sla_hours`.
* Remaining minutes = `floor((deadline - now) / 60)`.
* Status transitions:
  * `< 0` → `BREACH` (red)
  * `0 <= remaining <= warn_minutes` → `WARN` (yellow)
  * `remaining > warn_minutes` → `OK` (green)
* Trigger recalculations on shipment creation, node completion, and a cron job
  (`bin/recompute_sla.php`) that touches pending nodes every 5 minutes.

## 5. API Surface (`public/api.php`)

All requests hit a single entry point and dispatch via a `route` parameter. The
API replies with JSON and unified error payloads:

```json
{ "error": { "code": "FORBIDDEN", "message": "..." } }
```

### Authentication

* `POST auth.login` – `{email, password}`
* `POST auth.logout`
* `GET  auth.me`

### Template Management

* `GET  countries.list`
* `POST countries.create`
* `GET  country.nodes?country_id=`
* `POST country.nodes.save` – accepts an ordered array to upsert nodes.

### Shipments

* `POST shipments.create` – instantiates shipment nodes from the selected
  country template.
* `GET  shipments.list` – filterable by country, status, or search query.
* `GET  shipments.show?id=` – returns timeline data.

### Node Execution

* `POST nodes.done` – vendor submits ISO8601 actual time; validates previous
  completion and permissions.
* Optional: `POST nodes.upload` (multipart) and `POST nodes.sign` for future
  rollout.

### Audit & Reports (Phase 2)

* `GET nodes.events?node_id=`
* `GET reports.kpi?country_id=&from=&to=`

## 6. Front-End Wiring

* Template management page posts full node arrays to `country.nodes.save`.
* Shipments list exposes a "Create shipment" modal hitting `shipments.create`.
* Timeline cards pull data from `shipments.show` and trigger `nodes.done` for
  assigned vendors.
* Vendor task inbox aggregates their assignments via a filtered `shipments.list`
  or dedicated route.

## 7. Key Implementation Notes

* `services/Sla.php` supplies `computeForNode` and
  `recomputePendingForShipment` utilities.
* Enforce sequential completion by checking `sort_order - 1` before accepting a
  node submission.
* Store all timestamps in UTC; the UI renders Europe/Tirane via JavaScript
  `Intl.DateTimeFormat`.
* Event logging captures `SHIPMENT_CREATED`, `NODE_INSTANTIATED`, `NODE_DONE`,
  and SLA recomputations.

## 8. Initial SQL Migration

```sql
CREATE TABLE countries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(8) UNIQUE,
  name VARCHAR(64) NOT NULL
);

CREATE TABLE vendors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(128) NOT NULL,
  contact_email VARCHAR(128),
  active TINYINT DEFAULT 1
);

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vendor_id INT NULL,
  email VARCHAR(128) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('internal_admin','pm','vendor','approver','auditor') NOT NULL,
  active TINYINT DEFAULT 1,
  FOREIGN KEY (vendor_id) REFERENCES vendors(id)
);

CREATE TABLE country_nodes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  country_id INT NOT NULL,
  sort_order INT NOT NULL,
  name VARCHAR(128) NOT NULL,
  vendor_id INT NOT NULL,
  base_type ENUM('previous','eta','creation') NOT NULL DEFAULT 'previous',
  sla_hours DECIMAL(6,2) NOT NULL,
  evidence_required TINYINT DEFAULT 0,
  signature_required TINYINT DEFAULT 0,
  FOREIGN KEY (country_id) REFERENCES countries(id),
  FOREIGN KEY (vendor_id) REFERENCES vendors(id)
);

CREATE TABLE shipments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  country_id INT NOT NULL,
  code VARCHAR(64) NOT NULL,
  origin VARCHAR(16),
  eta_dest_airport DATETIME NOT NULL,
  meta_json JSON,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(country_id, code),
  FOREIGN KEY (country_id) REFERENCES countries(id)
);

CREATE TABLE shipment_nodes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  shipment_id INT NOT NULL,
  country_node_id INT NOT NULL,
  sort_order INT NOT NULL,
  name VARCHAR(128) NOT NULL,
  vendor_id INT NOT NULL,
  base_type ENUM('previous','eta','creation') NOT NULL,
  sla_hours DECIMAL(6,2) NOT NULL,
  actual_time DATETIME NULL,
  deadline_utc DATETIME NULL,
  status ENUM('PENDING','DONE') NOT NULL DEFAULT 'PENDING',
  sla_status ENUM('NA','OK','WARN','BREACH') NOT NULL DEFAULT 'NA',
  remaining_minutes INT NULL,
  FOREIGN KEY (shipment_id) REFERENCES shipments(id),
  FOREIGN KEY (country_node_id) REFERENCES country_nodes(id),
  FOREIGN KEY (vendor_id) REFERENCES vendors(id)
);
```

Tables for files, evidences, signatures, and events follow the same pattern and
can be added alongside optional features.

## 9. Acceptance Checklist

1. Configure ALB template with three nodes (landed, start customs, finish
   customs) and verify ordering validation.
2. Create a shipment and ensure timelines show ETA-based deadlines and
   dependency gating.
3. Attempt to complete nodes out of order; expect 409 responses.
4. Validate SLA status transitions around OK/WARN/BREACH thresholds.
5. Confirm role-based visibility for vendor and internal users.
6. Exercise file upload gatekeeping if the feature toggle is enabled.
7. Review event logs in UTC; UI renders Europe/Tirane.

## 10. Suggested Timeline

* **Week 1:** database migrations, authentication, core API endpoints, template
  editor wiring.
* **Week 2:** SLA engine, sequential enforcement, vendor inbox integration.
* **Week 3 (optional):** uploads, signatures, event timeline, simple reports.
* **Week 4 (optional):** notifications, evidence exports, hardened policies.

## 11. Key Trade-offs

* Focus on the simplified node model to achieve production readiness quickly.
* Architect services and repositories so advanced SLA rules (business hours,
  stop-clock) can layer on later without refactoring the UI.
```
