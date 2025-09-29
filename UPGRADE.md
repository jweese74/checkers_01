# Upgrading from the single-file release

The project has moved from a monolithic `index.php` to a modular layout rooted under `app/` with `public/index.php` as the only web entry point. Existing deployments can be upgraded in-place.

## Deployment steps

1. Deploy the new tree preserving the `data/` directory that contains `checkers.sqlite`.
2. Ensure the web server document root points to `public/` (or update rewrite rules to serve `public/index.php`).
3. Copy `config.php` if you customised paths or site metadata. New keys include security and pruning toggles with safe defaults.
4. On first request the bootstrap will run the SQL migrations found in `migrations/`. These migrations are idempotent and respect existing data.

## Behaviour notes

- All legacy URLs using `?id=...` continue to work. The router keeps the `action` parameter for backwards compatibility.
- Gameplay logic, move validation, and the SQLite schema remain compatible. The `games` table now includes additional columns for capability tokens, reminder flags, and scoreboard statistics.
- CSRF protection and per-side capability tokens are now enforced for move submissions. The front-end automatically sends the correct token so no manual action is required yet.
- Assets (CSS/JS) are served inline from `app/Assets/` to keep the shared-hosting footprint minimal while conforming to the CSP nonce emitted by the bootstrap.

## Testing after upgrade

Run `php tests/run.php` to execute the bundled smoke tests and confirm the rules engine and repository wiring behave as expected.
