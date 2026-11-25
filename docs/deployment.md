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
GitHub Actions verifies the backend in disposable containers. The workflow has
read-only repository access and no deployment jobs. The November 26 repository
audit found no GitHub webhooks or deployment records. Confirm external triggers
with the production operator before approving a merge that could initiate release.

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

## Migration and permission rollout

The release stack adds a completion-date report index (090000), audited
expenses (100000), and inventory planning policies (110000). First rehearse an
upgrade from a representative 34-migration backup in isolated staging.
Inspect index creation time and database locks. The target includes 37 migrations.
A disposable MySQL upgrade from 34 to 37 passed, including the RBAC seeder.

After the reviewed backup and an approved staging change window, apply the
migrations through the normal Laravel migration job. Run the reviewed
RbacSeeder once to provision owner permissions for sales reports, financial
reports, expenses and inventory planning. Verify the permissions against
the staging identity matrix. Never seed the development administrator.

## Backup and restore

1. Coordinate an application write pause or an atomic snapshot strategy so the
   database and attachments represent the same point in time. Capture the
   release SHA, applied migrations, MySQL version, and backup timestamp.
2. Create a MySQL logical dump with the operator's protected client option file.
   With a MySQL client compatible with the target server, use
   mysqldump --defaults-extra-file=<protected-file> --single-transaction
   --no-tablespaces --set-gtid-purged=OFF <database> > database.sql.
   Confirm the command completed successfully. Export any separately managed
   grants, routines or events using the operator's normal backup procedure.
3. Capture private purchase invoice attachments and public company assets.
   For local disks these are under storage/app/private and storage/app/public.
   Preserve disk identifiers and object paths for remote storage. Encrypt
   database and attachment archives at rest and record checksums and access
   controls. Record the reference to the existing APP_KEY in the secret
   manager so encrypted application data can be read after recovery.
4. Restore first to a new, empty isolated target. Verify host and schema
   using the protected target option file. Use mysql
   --defaults-extra-file=<target-option-file> <new-database> < database.sql.
   Restore the matching attachments and application secret references.
5. Compare schema versions, row counts, critical financial and stock totals
   and sampled attachment checksums against the backup manifest. Exercise
   authenticated reads and measure restoration time before approving recovery.

On 26 November 2026, an isolated MySQL-to-MySQL rehearsal restored a 58,266-byte
synthetic dump into a separate empty database. Verification matched 37
migrations, 24 permissions, the synthetic product and the new expense/planning
tables. Attachment recovery, production-sized RTO and RPO remain to be verified
in staging.

## UAT checklist

- [ ] Confirm owner, cashier and manager access using the approved matrix.
- [ ] Check Jakarta midnight boundaries, multiple lines in one sale, original
      units, cross-day returns, cancellation restatement, and CSV totals.
- [ ] Reconcile recorded sales and returns to the approved HPP example, confirm
      unknown historic cost remains unavailable, and review freight,
      discounts, expense audit and profit export with the business owner.
- [ ] Review zero-sales days, confirmed history, minimum fallback, reorder
      thresholds, and replenishment above the soft stock maximum.
- [ ] Confirm import preview/approval/commit using an approved staging workbook.
- [ ] Restore a representative staging backup, including attachments, and
      review reconciliation and recovery time.
- [ ] Confirm production change approval, monitoring ownership and threshold
      selection before any live operation.

## Candidate readiness

The stacked candidate includes sales reporting, provisional financial reports,
audited expenses and provisional stock planning. Test automation covers SQLite,
MySQL, concurrency, rollback, authorization, malformed import, formatting,
dependency checks and reporting route contract verification. Repository branch
and PR evidence is maintained in CODEX-HANDOFF.md.

Financial source fields for purchase returns, freight allocation and separate
discounts still require approved workflows. Data with unknown historical cost
is shown as unavailable. Accounting policy and the treatment of returns in
stock demand require sign-off. Query ranges and exports are capped, and the
sales completion query has a supporting index. Representative staging load and
ledger cutoff performance remain unmeasured. Production readiness also depends
on UAT, attachment restore verification and approval.
