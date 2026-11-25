# Codex persistent development checkpoint

Updated 26 November 2026, Asia/Jakarta. Read FIRST, then verify git status/log and
Docker. Continue existing implementation; do not restart the original audit.

## Recovery — 26 November 2026 (Asia/Jakarta)

- Checkout restored to D:\Project\ATK-Industry. Started from clean
  feat/backend-returns-reporting at f606e208, with implementation fd9e1b0.
- Current branch feat/backend-release-hardening, based on df99e86 (draft PR #30).
- Verified active GitHub login thallasff with upstream push/admin access.
  PRs #23–#27 are open. #23 is historical foundation from Lloyd-29.
  #24 includes #23's foundation commit. #25 is stacked on #24.
  API verification confirms #23's source branch at 16db79a.
  #26 is the v1.4 business draft. #27 is the owner's dashboard UI work.
- Recovery baseline supplied by operator: SQLite 304 pass / 3 skip / 1036
  assertions, MySQL 307 pass / 1047 assertions, Pint 201 files and platform checks
  pass, 34 migrations applied, API health pass. Docker services observed up with
  development MySQL healthy. Baseline regression was not repeated during recovery.
- Draft backend requirements include effective-date sales/return recognition,
  historical cost, received purchases and freight, expense reconciliation,
  and fixed-lead-time safety stock with full calendar-day observations. Final
  accounting and historical demand policies remain pending business approval.
- Milestone A implementation 04b55eaf141404632eb80e987cb83257f205530a on feat/backend-sales-reporting.
  SQLite 308 pass / 3 MySQL-only skip / 1083 assertions, MySQL 311 pass /
  1094 assertions. Pint 206 files, platform requirements and Composer audit
  passed. Focused tests: 4 / 47 assertions per engine.
- Daily sales query, controller/request, route/RBAC, regression tests and
  API Markdown/OpenAPI contract. Additive migration 2026_11_26_090000
  adds a sales completion date index and ran in isolated test databases.
- Cancellation restates the original day. Returns use effective date.
  PR #28 is open and stacked on #25.
- Milestone B implementation 23966c4faa97f2faacc3f696bed21d16d562222b on feat/backend-financial-reporting
  (base PR #28). SQLite 314 pass / 3 skip / 1141 assertions, MySQL 317 pass /
  1152 assertions, Pint 216 files, platform requirements and locked audit pass.
  Migration 2026_11_26_100000 adds expenses and immutable revisions.
  Financial source limitations: unclassified freight, no recorded purchase
  returns/discounts, no separately recorded sales discounts. Requires accounting
  review. Draft PR #29 is stacked on PR #28.
- Milestone C implementation 90e1bc0eb668a698cb491547958dbb1795673ab8 on feat/backend-inventory-planning
  (base draft PR #29). SQLite 317 pass / 3 skip / 1182 assertions, MySQL 320
  pass / 1193 assertions, Pint 222 files and Composer platform/audit pass.
  Additive migration 2026_11_26_110000 adds inventory planning policies.
  Return demand treatment and 95% service level remain provisional.
  Draft PR #30 is stacked on #29.
- Milestone D WIP. Added isolated test-only Actions workflow, authorization
  and API contract checks, backup/restore checklist and UAT. MySQL synthetic
  dump restore passed with 37 migrations and 24 permissions. Next: full
  Compose run, staged migration upgrade check and CI verification.
## Repository / GitHub
- Path D:\Project\ATK-Industry, backend apps/api.
- Branch feat/backend-returns-reporting, tracking origin/feat/backend-returns-reporting.
- Implementation HEAD fd9e1b0a4216ee72fe6ebb7dfc810b40f9f73cfe, pushed to origin.
  A subsequent handoff-only commit may be HEAD; git log is authoritative.
- Working tree at milestone: implementation committed and pushed; this handoff update only.
- origin https://github.com/MBA-Corp/ATK-Industry.git (HTTPS).
- fork https://github.com/Lloyd-29/ATK-Industry.git retained historically; do NOT use
  for new work. No historical authors rewritten.
- Confirmed account thallasff; gh api user verified; push/admin true.
- gh auth setup-git done. Local author thallasff
  <181099147+thallasff@users.noreply.github.com>.
- Lloyd-29 stored but inactive. Verify account/permission before new commits/pushes.
- PR #23 OPEN: https://github.com/MBA-Corp/ATK-Industry/pull/23 (foundation).
- PR #24 OPEN: https://github.com/MBA-Corp/ATK-Industry/pull/24 (data/valuation;
  includes foundation commit from #23 and explicitly documents that dependency).
- No merge or production operation performed.

## Completed milestones
### Foundation PASS — 16db79a
Inactive session/token rejection (EnsureActiveUser + InactiveSessionTest), targeted
Laravel/Symfony advisory fixes (Laravel 13.43.0), Docker development setup, isolated
compose.test.yaml runner, GD and SQLite build fixes, InventoryController Pint fix.
Boost dev-only installed per original AGENTS prerequisite. Current AGENTS replaces
host-PHP bootstrap with Docker engineering rules.

Docs: testing.md, deployment.md, architecture/backend-hardening.md,
decisions/accounting-policy.md, backend-roadmap.md.

### Catalog import / valuation foundation PASS — f134390
Main files under apps/api:
- Actions/Catalog/ImportCatalogAction, Support/CatalogWorkbookReader,
  Controllers/Api/V1/CatalogImportController.
- Actions/Inventory/RecordInventoryValuation, Support/CostMath.
- ApplyStockMovementAction hook, ReceivePurchaseAction acquisition value,
  CancelSaleAction original cost restoration.
- CatalogImportTest, InventoryValuationTest, MySqlStockConcurrencyTest,
  tests/Support/stock-worker.php, guarded tests/TestCase.php.
- RbacSeeder catalog.import permission; routes/api.php import endpoints.
- composer.json/lock explicitly require ext-bcmath.
- docs/architecture/catalog-import-valuation.md is the detailed contract.

Importer implements bounded XLSX ZIP/XML parsing (Satuan), formula/entity rejection,
preview row errors/warnings, explicit to_base conversions and disable_sales zero-price
policy, atomic commit/hash/idempotency and audited opening stock.
Original MEREK/SUPPLIER/RAK/STOKMIN metadata is retained in import rows, not silently
converted into supplier associations or planning rules. No real client files found;
synthetic fixtures only. No updates/overwrites of existing products.
Endpoints POST /api/v1/catalog-imports/preview and /commit require catalog.import.

Valuation uses decimal BCMath and nullable unknown costs. Stock and value are atomic
under the same product lock. Sale cost is snapshotted; cancellation restores it.
Unknown incoming/legacy cost stays unknown. Positive adjustment without evidence
invalidates blended cost; zero stock clears residual value. Ledger is processing-order,
not retrospective revaluation based on backdated occurred_at.
Policy moving_average_excluding_freight_provisional is NOT official accounting.

### Returns/refund PASS — fd9e1b0
PR #25 OPEN: https://github.com/MBA-Corp/ATK-Industry/pull/25, stacked on PR #24 branch.
ReturnSaleAction, RecordManualRefundAction, SaleReturnController, StoreSaleReturnRequest;
new 2026_11_18_120000_create_sales_returns migration creates sale_returns,
sale_return_items and sale_refunds. Original sales/payments immutable. Sale/item locks,
cumulative refund/base/cost allocation, damaged-vs-resellable treatment, no full
cancellation after returns. Manual refunds have evidence and MANUALLY_RECORDED status,
never a claim of external payout verification. RbacSeeder adds sale.return/refund.record.
Tests: SaleReturnTest, MySqlReturnConcurrencyTest, RetailFixture, return-worker.php.
Contract: docs/architecture/sales-returns.md.
Full SQLite: 304 passed, 3 MySQL-only skipped, 1036 assertions.
Full MySQL: 307 passed, 1047 assertions. Pint 201 files/platform PASS.
Local migration upgrade PASS (34 total), health HTTP 200.

## Database / migrations
Development: atk_industry_dev on Compose mysql, persistent mysql_data volume.
34 migrations applied, including:
- 2026_11_18_100000_add_inventory_valuation_foundation:
  inventory_costs, inventory_valuations, nullable sale_items.cost_total DECIMAL(30,6).
- 2026_11_18_110000_create_catalog_imports:
  catalog_imports and catalog_import_rows.
31 historical migrations unchanged. Development pre-migration counts verified
users=0/products=0/sales=0. No production database accessed.
New permission can be provisioned by reviewed RbacSeeder; no local demo user seeded.

## Actual test results
- Original baseline: SQLite/MySQL each 286 tests / 971 assertions PASS.
- Foundation: SQLite/MySQL each 288 / 975 PASS; Pint 181 files PASS;
  Composer audit zero advisories; platform checks PASS.
- Data milestone full standalone runner:
  SQLite: 300 total, 299 passed, 1 MySQL-only test skipped, 1007 assertions.
  MySQL 8.4: 300 passed, 1012 assertions (real two-process stock contention included).
  Pint: 192 files PASS. Platform checks including BCMath PASS.
  Composer validate PASS; audit zero advisories; local health HTTP 200.
- Focused precursor: valuation/receiving/cancellation 19 tests / 66 assertions PASS;
  import + valuation 11 / 32 PASS. Early integration null-return bug fixed before full run.
- Concurrent test checks one of two competing withdrawals succeeds, other rejects,
  balance/value and ledger remain consistent. It does not establish all future
  return/payment race behavior; add tests as those features are implemented.

## Docker / commands
API atk-industry-dev-api-1 up on 127.0.0.1:8000, mysql healthy (8.4.13).
PHP 8.3.36, Composer 2.11.2, lock Laravel 13.43.0.
Verification containers removed after successful run; development volume untouched.
No frontend running.

From repository root:
```powershell
docker compose up -d --build
docker compose ps
docker compose exec -T api php artisan migrate:status
docker compose -p atk-verification -f compose.test.yaml up --build --abort-on-container-exit --exit-code-from api-test --attach api-test
docker compose -p atk-verification -f compose.test.yaml down
```
Standalone tests use their own network and tmpfs MySQL/vendor/storage; no dev volume.
TestCase rejects any DB except SQLite :memory: or mysql-test/atk_industry_test/atk_test.
NEVER use development MySQL for PHPUnit, or down --volumes on development.
Pint --dirty does not work inside apps/api mount (no .git); use explicit paths or
full vendor/bin/pint --test.

## Pending risks / business decisions
- MWA/freight approval pending: older CD-002 excludes freight while periodic example
  includes it. Preserve the distinction; never claim provisional profit official.
- Unknown legacy opening valuations must not be fabricated or backfilled from current
  catalog prices. Damaged-return cost handling must be explicit.
- Receiving is ONE receipt per purchase; do not claim partial/repeated receipts.
- Original catalog conversion validation still permits excess precision and float
  boundary handling; add regressions before tightening it.
- Import API integration coverage and malformed-file matrix can be expanded during
  final hardening. Real workbook formats/mappings require user acceptance.
- No workflow/webhook/deployment entries returned by GitHub API under thallasff.
  External deployment polling cannot be ruled out; no merge/live deployment authorized.
- Release still requires complete scope below, security review, backup/restore
  rehearsal, monitoring, load/concurrency review and UAT. Green tests are not enough.

## Next work, in order
1. DONE in fd9e1b0: Partial/full sales returns separate from full cancellation. Sale/line locks,
   cumulative quantity/value allocation, idempotent request, damaged vs resellable,
   audited stock restock. Block full cancellation after any return.
2. DONE in fd9e1b0: Separate manual refund records/status/evidence; never imply gateway payment success.
   Optional exchange references a replacement sale, not hidden netting.
3. Reports: Jakarta day boundaries [start, next-day start), daily SKU/base-unit
   quantity, original-unit detail, transaction vs line counts, cancellation/return
   reconciliation; consistent date_from/date_to, filters/sorts/pagination and CSV.
4. Provisional product/transaction margin, periodic/perpetual HPP reconciliation,
   operating expenses with audit history and simple net profit. Test supplied
   periodic example: net purchases 5,050,000; HPP 10,050,000; net sales 15,700,000;
   gross 5,650,000; simple net 3,950,000. No double-counted HPP.
5. Stock planning: 95%, Z=1.645, daily zero-sales observations, fixed lead-time
   assumptions, minimum history/manual fallback, ROP, max-stock soft warning.
6. Full regression/concurrency/security and operational release review.

## Resume protocol
Read current diff + AGENTS + this file. Verify thallasff and origin push permission.
Do not recreate importer/valuation or reapply old setup. Continue on a suitable
feature branch with incremental commits. Every milestone requires relevant tests,
MySQL, Pint, migration/contract verification, reviewed diff, commit/push/PR.
Update this file with exact SHA/results and WIP before stopping. Preserve unfinished
changes, never push failed code as verified, never commit secrets/client source files.
User authorized backend engineering/feature branches/PRs, not frontend or production.

Next implementation starts at item 3 (reporting), then finance/planning. Do not recreate returns/refunds. The return migration is already applied locally. Continue maintaining exact checkpoint results.
