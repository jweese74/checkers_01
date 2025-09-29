# Simple Checkers

A self-contained PHP 8.1+ checkers application backed by SQLite. The project has been modularised to keep the public surface to `public/index.php` while the application logic lives under `app/`.

## Requirements

- PHP 8.1 or higher with SQLite3 extension enabled.
- Ability to write to the `data/` directory for the SQLite database file.

## Running locally

```bash
php -S localhost:8000 -t public
```

Visit <http://localhost:8000> to start a new game or resume an existing one.

## Configuration

Configuration values live in `config.php`. By default the application stores the SQLite database at `data/checkers.sqlite`. Adjust the `database_path` or other settings as needed. The bootstrap will create the data directory automatically.

## Database migrations

Migrations are plain SQL files located under `migrations/` and are applied automatically on startup. Migrations are idempotent; the runner keeps a `schema_version` table so re-deployments leave existing data intact.

## Testing

A lightweight test harness is provided:

```bash
php tests/run.php
```

The script covers core rules, serialisation, repository persistence, and the router mapping.

## Assets

Client-side assets live under `app/Assets/`. They are embedded inline by the layout template to keep the deployment footprint small while honouring the configured CSP nonce.

## Security features

- CSRF protection via session-backed token.
- Capability tokens generated per-side for move submissions (currently scaffolded, wired through the API).
- Security headers (CSP with nonce, X-Content-Type-Options, Referrer-Policy, Permissions-Policy, X-Frame-Options).

## Streaming updates

An SSE endpoint is exposed via `?action=stream&id=...`. The browser script will attempt to use SSE and falls back to periodic polling when EventSource is unavailable.
