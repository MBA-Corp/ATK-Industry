# Independent backend release candidate review

**Disposition is BLOCKED for integrated staging.** Candidate reviewed at a55deca on 26 November 2026 (Asia/Jakarta). An isolated backend is available for simulation.

| PR | Review focus | Result |
|---|---|---|
| #23 | Sanctum active-account enforcement, login throttle, dependencies, disposable Docker | Active-user guard verified. Older sales routes need a role/scope decision. |
| #24 | Catalog import, stock movement, MWA valuation, migration | Proven stale snapshot and first-balance defects fixed. |
| #25 | Return and cancellation locking, refund uniqueness, replay, rounding | Same-sale locks and constraints in place. Return/refund wire contract corrected. |
| #28 | Daily sales, UTC bounds, counts, CSV, reporting index | Report boundaries work for UTC data. Upstream offset handling fixed. |
| #29 | Historical snapshots, receipt recognition, two HPP methods, expense audit | Receipt cutoff regression added. Cross-period cancellation and accounting inputs remain open. |
| #30 | Demand observation, base-unit identity, alerts and pagination | Guard prevents relabeling existing stock/history. Demand policy remains provisional. |
| #31 | CI, OpenAPI parity, migration, restore and deployment gates | Disposable test CI passed on the original candidate. Representative staging data, workload and attachments still need verification. |

#24 contains the foundation commit from #23. Review that foundation once, then the incremental #24 delta, followed by #25 → #28 → #29 → #30 → #31 → review fixes.

## Critical

No confirmed Critical issue in the reviewed scope. Production topology and credentials were outside the review.

## High

1. **Stale MySQL cost and balance initialization — repaired.** In a55deca, apps/api/app/Actions/Inventory/RecordInventoryValuation.php:15 read a snapshot and ApplyStockMovementAction.php:70 used firstOrCreate before reading the locked balance. Reproduction uses two MySQL connections. After the reader sees 10 units at Rp1.000, another connection commits 10 units worth Rp2.000. Selling 10 from the first transaction wrote HPP Rp500 instead of Rp1.500. A separate case where the balance first appeared after the snapshot raised MySQL 1062. Use current reads under the product lock and native cost upsert. Both cases are asserted by the new test.
2. **An explicit timestamp offset moved records across the Jakarta cutoff — repaired.** Original CreateSaleAction.php:134 and ReceivePurchaseAction.php:59 sent the parsed local wall time to storage. Sale at 2026-11-25T23:55:00+07:00 produced an empty November 25 result. A receipt for 2 × Rp5.000 returned gross purchases Rp0 for that day. Normalize to 16:55 UTC before persisting sales, receipts and adjustments and assert matching ledger timestamps. Audit existing records against source timestamps before any backfill.
3. **Catalog edit could relabel existing stock and history — repaired.** Original apps/api/app/Actions/Catalog/UpdateProductAction.php:79–181 allowed replacing PCS with BOX while the balance remained numerically 7. The feature test received HTTP 200. Reject a base-unit change when stock, movement history or a planning policy already exists. Any conversion of existing data needs a reviewed migration of units and history.
4. **Cross-period cancellation leaves unmatched periodic HPP — open.** QuerySalesReport.php:58–65 restates the original sale day. CancelSaleAction.php creates the stock reversal on cancellation day. ReportFinancials.php:22–31 calculates actual value cutoffs and excludes SALE_REVERSAL from the exception count. Reproduce with opening inventory Rp1.000 before day A, a sale at cost Rp300 on A and cancellation on B. The isolated MySQL API probe reproduced this on 26–27 November. Net sales and perpetual HPP were zero on both days, while periodic HPP was Rp300 and −Rp300 respectively. Both reports returned requires_review with non_sales_or_purchase_movements equal to zero. Approve cancellation attribution and add a two-period reconciliation test before financial UAT.
5. **Checkout pricing and sales visibility lack an approved permission boundary — open.** routes/api.php:84–110, StoreSaleRequest.php:67 and CreateSaleAction.php:94. A synthetic cashier with only sale.create recorded a sale at Rp1 against catalog Rp101. A separate active account without a role received 200 for sales list, detail and receipt for the owner's sale. The existing SaleCheckoutApiTest.php:245 confirms that price overrides are intentional at the endpoint level. The role/price limits remain undefined. The API contract Price Authority section anticipates a permission check. Approve the non-owner matrix and enforce it with integration tests before enabling real operator access.

## Medium

- **Return OpenAPI described a different API — repaired.** Original docs/architecture/api-contract-v1.0.yaml:932 advertised POST /sale-returns, HTTP 201 and return_quantity. Compare routes/api.php:53–55 and StoreSaleReturnRequest.php:17–24. The deployed shape is sale-scoped POST with quantity/condition and HTTP 200. OpenAPI now includes create, detail, manual refund, and their middleware probe. Frontend consumption still needs contract tests.
- **Checkout retry cannot retrieve the prior result — open.** StoreSaleRequest.php:20–28 rejects a reused client_reference, and SaleController.php:113–134 handles a database conflict as 409. In an isolated fixture, replay a completed checkout with the same key. Duplicate stock withdrawal is prevented, but the terminal receives no existing receipt. Agree a lookup/replay contract before enabling offline retries. Return/refund replay requires the same actor, key and payload.
- **Formula test does not cover unavailable accounting inputs — open.** ProfitAmountsTest.php:10–22 injects freight, purchase returns and discounts. ReportFinancials.php:34–45 uses placeholders and declares source capability flags. Compare an API report against a source-backed purchase/discount/return fixture after approval. The receipt test added in this review does cover actual received quantity and HPP at a day boundary.
- **Ledger cutoffs and checkout query counts need staging measurements — open.** ReportFinancials.php:22–23,105–118 groups the movement history twice. StoreSaleRequest.php:117–134 reads each product unit separately. Exercise a long history and a large multi-line basket, capture SQL counts and EXPLAIN ANALYZE. The 10.000-row index check validated one sales branch, which leaves the full return union, pagination count, and ledger aggregation unmeasured.
- **Frontend reports still use fixture data — open.** apps/web/app/routes/reports.tsx:20–29,58–63 reads report.mock. Opening the page exercises mock filtering. Wire the v1 endpoints and verify pagination, permissions, signed Rupiah strings, provisionally unavailable values and CSV before integrated staging.

## Low

- **SQLite is a narrower precision oracle — open test limitation.** ReportFinancials.php:140–149 scales financial amounts to signed integers in SQLite. Try an aggregate near Rp9.22 trillion at six decimal places. At that limit SQLite can overflow while MySQL uses DECIMAL. The existing large-cost test covers arithmetic alone. Add MySQL boundary tests for store, aggregate and export.
- **Index migration locking remains unmeasured at scale — open validation.** 2026_11_26_090000_index_sales_completion_for_reporting.php:11 adds an index on sales. Run the 34→37 upgrade on a representative restored copy under synthetic concurrent traffic. Capture metadata lock time and confirm the roll-forward plan.
- **Mixed cancel-versus-return interleaving is untested — open.** MySqlReturnConcurrencyTest.php:40–60 tests return-versus-return and refund-versus-refund. CancelSaleAction.php:37–60 uses the same sale lock. Contend a cancellation and a return on one synthetic sale and verify one stock restoration and no duplicate outflow.

## Integration and staging checklist

### Frontend team

- [ ] Wire reports to versioned API endpoints. Test the reporting pagination envelope, CSV, nullable provisional values, and alert cursors on empty windows.
- [ ] Use Jakarta date filters and explicit event offsets. Test midnight, next-day returns, prior-day cancellations and original unit snapshots.
- [ ] Use the corrected return/refund routes. Retain the same idempotency key across transport retries. Handle 200 replay, 409 conflict, 422 validation and 403 access denial. Treat manual refund status as operator evidence.
- [ ] Test checkout timeouts and duplicate client_reference outcomes. Enable offline retries after agreeing on the replay contract.
- [ ] Execute the agreed owner, manager and cashier permission matrix using synthetic accounts.

### Server and business owners

- [ ] Review the stacked PRs in order and identify the exact staging SHA. Confirm staging isolation, credentials, logging and external deployment triggers.
- [ ] Approve pricing and role visibility along with cross-period HPP recognition and treatment of pending financial sources. Exercise a source-backed golden fixture.
- [ ] Rehearse MySQL migration and permission seeding on a representative copy. Capture locks and compare stock/value counts before and after.
- [ ] Restore DB, attachments and secret references into an empty staging target. Check attachment permissions and checksums and measure RTO/RPO.
- [ ] Run authenticated smoke and representative checkout/report load. Record ledger and report query plans, latency, and cross-operation concurrency.
- [ ] Confirm UAT sign-off and follow the separate production approval process.
