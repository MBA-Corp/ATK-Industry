# Codex persistent development checkpoint

Updated: 18 November 2026 (Asia/Jakarta). Read this FIRST in a new session,
then verify git status/log and Docker; do not repeat completed implementation.

## Repository and identity
- Path: C:\Projects\ATK-Industry\ATK-Industry; backend apps/api.
- Current branch: feat/backend-data-finance.
- HEAD at this checkpoint: 16db79a4958011df7b197c7322d10a6049391aed.
- HEAD is the completed foundation milestone, not the current uncommitted data work.
- origin: https://github.com/MBA-Corp/ATK-Industry.git (HTTPS).
- fork: https://github.com/Lloyd-29/ATK-Industry.git; historical remote, do not use for new pushes.
- Confirmed account: thallasff. gh api user verified; repository push=true/admin=true.
- gh auth setup-git completed. Repository-local author:
  thallasff <181099147+thallasff@users.noreply.github.com>.
- Lloyd-29 remains stored but inactive; never switch to it implicitly.
- Before every new commit/push verify gh api user and repository permissions.
- No historical authors rewritten; no main merge or production changes.

## Completed milestone
Foundation PASS, commit 16db79a, pushed as fork/feat/backend-release-foundation.
Open upstream PR: https://github.com/MBA-Corp/ATK-Industry/pull/23.
- Inactive sessions/tokens now rejected by EnsureActiveUser middleware and route group.
- InactiveSessionTest demonstrated two failures before fix, then passed.
- Patched advisory-affected Laravel/Symfony: Laravel 13.43.0.
- Laravel Boost dev-only installed because original AGENTS.md required it.
- Dockerfile.dev fixed duplicate built-in SQLite compilation; GD/JPEG/FreeType installed.
- compose.yaml development environment; compose.test.yaml isolated verification.
- InventoryController formatting fixed.
- AGENTS.md updated; docs/testing.md, docs/deployment.md,
  docs/architecture/backend-hardening.md, docs/decisions/accounting-policy.md,
  docs/backend-roadmap.md added.

## Current uncommitted milestone — PARTIAL, do not label release-ready
Valuation and safe catalog import code exists locally; NOT pushed, NOT fully verified.
Modified tracked files:
- app/Actions/Inventory/ApplyStockMovementAction.php: optional incomingValue; atomic valuation hook.
- app/Actions/Purchasing/ReceivePurchaseAction.php: acquisition value from received quantity/price.
- app/Actions/Sales/CancelSaleAction.php: reverse stored sale cost.
- database/seeders/RbacSeeder.php: catalog.import permission.
- routes/api.php: permission-protected import endpoints.

New files under apps/api:
- app/Actions/Inventory/RecordInventoryValuation.php
- app/Support/CostMath.php
- app/Actions/Catalog/ImportCatalogAction.php
- app/Support/CatalogWorkbookReader.php
- app/Http/Controllers/Api/V1/CatalogImportController.php
- tests/Feature/InventoryValuationTest.php
- tests/Feature/CatalogImportTest.php
- database/migrations/2026_11_18_100000_add_inventory_valuation_foundation.php
- database/migrations/2026_11_18_110000_create_catalog_imports.php

Valuation uses decimal strings/BCMath, unknown opening cost remains null, records
before/delta/after alongside stock movements, snapshots cost_total onto sale_items.
Freight excluded provisionally; cost ledger follows processing order, not retroactive
recalculation from occurred_at. Positive adjustment without known cost invalidates
blended value. Zero inventory clears residual value. Policy is NOT approved accounting.

Importer: bounded ZIP/XML XLSX reader, Satuan sheet, formulas/entities rejected,
preview diagnostics, explicit to_base conversion and disable_sales zero-price policy,
atomic commit with hash/idempotency key, audited opening stock, original metadata retained
in database only. No real uli.xlsx/PDF found; tests use synthetic records.
Endpoints POST /api/v1/catalog-imports/preview and /commit require catalog.import.
Still needs broader edge-case/API/concurrency review and contract documentation.

## Database
Development database atk_industry_dev on Compose mysql, persistent mysql_data volume.
31 historical migrations applied. Two new migrations above PENDING in development.
New tables: inventory_costs, inventory_valuations, catalog_imports, catalog_import_rows.
New nullable sale_items.cost_total DECIMAL(30,6). No historical migration modified.
No new migration run against production. Do not point RefreshDatabase at development.
Before development migration verify local host/schema and current data; preserve it.

## Actual verification results
- Original baseline b1d0c8f: SQLite 286 tests / 971 assertions PASS.
- Completed foundation: SQLite 288 / 975 PASS; MySQL 8.4 288 / 975 PASS.
- Foundation Pint: 181 files PASS; Composer platform PASS; audit zero advisories.
- Current WIP focused SQLite tests:
  InventoryValuationTest + PurchaseReceivingInventoryTest + SaleCancellationApiTest:
  19 tests / 66 assertions PASS.
  CatalogImportTest + InventoryValuationTest: 11 tests / 32 assertions PASS.
- FULL SQLite/MySQL suites for CURRENT WIP have NOT run yet.
- Current WIP Pint, migration upgrade verification, API smoke, and Composer BCMath
  platform declaration remain to be completed.
- An early hook-edit bug returned null; fixed locally, then focused tests above passed.

## Runtime
Last verified: API up, localhost:8000; mysql healthy, MySQL 8.4.13.
PHP 8.3.36 / Composer 2.11.2; current lock Laravel 13.43.0.
Development containers: atk-industry-dev-api-1, atk-industry-dev-mysql-1.
Isolated verification containers were removed after foundation checks; development
volumes preserved. No frontend running.

From repository root:
```powershell
docker compose up -d --build
docker compose ps
docker compose exec -T api php artisan migrate:status
docker compose -p atk-verification -f compose.test.yaml up --build --abort-on-container-exit --exit-code-from api-test
docker compose -p atk-verification -f compose.test.yaml down
```
The standalone test project has its own network, tmpfs database/vendor/storage,
no development volume and no exposed port. Its down is safe for those disposable
resources. NEVER use down --volumes on development.
Use explicit filenames with Pint inside development container; --dirty fails because
the repository .git directory is outside the apps/api bind mount.

## Review items and pending policy
- MWA/freight treatment pending owner/accounting approval. Never call outputs official.
- Review exact snapshot restoration and costs on unknown/damaged returns.
- Declare ext-bcmath in composer.json/lock before shipping valuation.
- Tighten importer case-insensitive duplicates, inactive category mapping, duplicate
  idempotency concurrency response, XLSX namespace/limit/error cases.
- Existing catalog conversion validation still accepts excessive decimal precision;
  add regression tests before changing. Legacy float acceptance needs careful review.
- No GitHub workflow, webhook or deployment records returned for repo under thallasff.
  External polling/deployment cannot be ruled out. No merge/live deploy authorized.
- PR #23 is still OPEN; feature branch includes its commit. New PR must disclose dependency.
- No real client source files located; never invent SKU history from daily PDF totals.

## Remaining scope
1. Finish catalog importer/valuation foundation and full quality gates.
2. Jakarta date ranges, product/day sales reports and purchase/sales summaries/export.
3. Partial/full sales returns separate from cancellation; manual refund records,
   idempotency, concurrent over-return prevention, damaged goods vs restock.
4. Provisional margins, HPP reconciliation, operational expenses with audit trail.
5. Statistical safety stock 95%, Z=1.645, fixed lead-time assumptions, zero-demand days,
   minimum history/manual fallback, ROP, maximum stock soft warnings.
6. Full MySQL concurrency/regression/security review, API contract, operational release
   gates, backup/restore/UAT. Green tests alone do not mean production-ready.

## Concrete resume sequence
1. Read this file, apps/api/AGENTS.md and current git diff. Preserve all WIP.
2. Verify gh api user returns thallasff and origin repository push permission is true.
3. Review/fix WIP issues above; do not duplicate importer or valuation code.
4. Finish feature tests, docs and migration verification in isolated MySQL.
5. Run full SQLite/MySQL, Pint, Composer audit/platform and local health.
6. Review diff, commit only valid milestone changes on feat/backend-data-finance.
7. Push to origin (not old fork); open PR, noting dependency on PR #23 if still open.
8. Update this checkpoint with exact results, SHA, push/PR and next milestone.
9. Continue remaining scope; no frontend, production, force-push or destructive resets.

Checkpoint rule: update at EVERY milestone and BEFORE stopping. If work is incomplete,
list files, errors and next commands. Never claim unrun tests passed. HEAD here describes
the implementation checkpoint before any subsequent handoff-only commit; verify git log.
