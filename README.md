# Logistics SLA Console Prototype

This repository provides a PHP-based prototype that mirrors the Logistics SLA front-end blueprint. It uses lightweight helpers to render Blade-style templates with Tailwind CSS and Alpine.js delivered via CDN, enabling quick reviews without requiring Composer dependencies.

## Structure

- `public/index.php` – entry script with a simple router to switch between IA sections.
- `src/functions.php` – tiny view helpers used to emulate Blade layout rendering.
- `src/data.php` – mocked data sources powering the UI states across pages.
- `resources/views/` – Blade-like templates implementing each blueprint screen:
  - Authentication (`auth/`): login, MFA, first-time setup flows.
  - Navigation layout (`layouts/`): authenticated shell and guest layout.
  - Domain pages: projects catalogue, shipments list & detail timeline, vendor task inbox, template configurator, KPI reports, and system settings.
- `docs/` – documentation assets (country pack JSON, field dictionary, UAT scenarios, and implementation guidance).

## Usage

Serve the `public/` directory with PHP's built-in server:

```bash
php -S 0.0.0.0:8000 -t public/
```

Then open `http://localhost:8000/index.php?page=projects` to browse the prototype. Use the `page` query string to switch between views (`login`, `mfa`, `first-setup`, `projects`, `shipments`, `shipment-detail`, `tasks`, `templates`, `reports`, `settings`).

## Notes

- All timestamps are rendered in Europe/Tirane with UTC tooltips to reflect the time strategy.
- SLA countdowns display the remaining minutes based on mocked data and include explain modals that outline the calculation basis.
- Upload, signature, and reassignment interactions are presented as interactive placeholders using Alpine.js to demonstrate flows without backend wiring.
- The UI follows the provided color tokens (green/yellow/red/gray) and component guidance (timeline cards, evidence lists, countdown badges, modal dialogs).

## Further Reading

- [End-to-End Implementation Plan](docs/dev_plan.md)
- [SQLite 全栈开发清单 v2](docs/sqlite_checklist_v2.md)

This foundation can be swapped into a full Laravel application by replacing the helper renderer with native Blade, wiring controllers, and connecting to real data sources.
