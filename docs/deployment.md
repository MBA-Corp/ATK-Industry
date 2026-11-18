# Backend operations and release gates

## Local development
Use the existing compose.yaml. Create root .env with independently generated
MYSQL_PASSWORD and MYSQL_ROOT_PASSWORD; keep it out of Git. Copy apps/api/.env.example
to apps/api/.env only if absent; generate APP_KEY once, never overwrite an existing key.

```powershell
docker compose up -d --build
docker compose exec -T api php artisan migrate:status
# Only after verifying this is the intended safe local database:
docker compose exec -T api php artisan migrate --no-interaction
```

API: http://localhost:8000/api/v1/health. The endpoint checks HTTP/Laravel liveness,
not MySQL readiness. MySQL has a separate Compose health check.
The development server is not a production server. MySQL data persists in mysql_data.
Never run down --volumes on an existing environment.
LocalAdminSeeder is development/test only; do not use its sample credentials online.

## Production handoff — pending operational approval
No live deployment performed. No repository Actions workflow found, but external
webhooks could not be inspected. Do not merge until an operator identifies deployment
triggers and authorizes any production effect.

Use a production PHP runtime/web server with APP_ENV=production, APP_DEBUG=false,
HTTPS, secure session cookies, correct Sanctum domains and trusted proxy/host settings.
Install with composer install --no-dev --prefer-dist --optimize-autoloader.
Do not expose Boost/MCP development tooling or the development Compose server.
Provision least-privilege database and separate migration credentials.

Before migrations: back up database and attachments together, record schema/version,
encrypt/restrict backups, and rehearse restoration in an isolated staging environment.
Review additive migrations for locks and large-table runtime. Run migrations once in
a controlled release step, then liveness, database and authenticated API smoke tests.
Do not use migrate:fresh, reset or rollback as an automatic recovery strategy.
Prefer roll-forward; restore requires explicit data-loss approval and a verified backup.

Monitor HTTP errors, latency, database failures, backup age, storage capacity and
stock/financial reconciliation anomalies. Assign alert ownership. Load/concurrency,
backup restore, UAT and accounting policy approval remain release gates.
