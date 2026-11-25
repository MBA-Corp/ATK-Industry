# Backend checkpoint

Updated 26 November 2026, Asia/Jakarta.

## Current state

- Repository MBA-Corp/ATK-Industry at D:\Project\ATK-Industry, backend apps/api.
- Branch fix/backend-release-review, based on candidate a55deca from draft PR #31. Review implementation 4a22996, published in [draft PR #32](https://github.com/MBA-Corp/ATK-Industry/pull/32). Use git log -1 for current HEAD.
- Active contributor thallasff. The Lloyd-29 fork is historical.
- Independent disposition BLOCKED for integrated staging. See [review and team checklists](backend-release-review.md).
- Review changes include current-read stock costing and initial balance handling, UTC normalization, base-unit safety, and the return/refund OpenAPI correction. The review adds no migrations.
- Open gates include cross-period financial recognition, checkout pricing and sales visibility, frontend API integration, representative staging load and attachment restore. Accounting source workflows and production approval remain open.
- SQLite passed 323 tests with four MySQL-only skips and 1219 assertions. MySQL passed 327 tests and 1234 assertions. Pint 224 files, Composer platform/audit, and the 16-operation contract passed. [Review PR CI passed for 4a22996](https://github.com/MBA-Corp/ATK-Industry/actions/runs/45720856021).
- Next action is to review the fix PR, agree the remaining permission and accounting decisions, and rehearse on isolated representative staging.

## Historical evidence

| PR | Implementation SHA | Scope |
|---|---|---|
| #23 | 16db79a | Authentication, Docker, isolated tests |
| #24 | f134390 | Catalog and valuation, includes #23 |
| #25 | fd9e1b0 | Partial returns and manual refunds, stacked on #24 |
| #28 | 04b55ea | Sales reporting, stacked on #25 |
| #29 | 23966c4 | Provisional financials and expenses, stacked on #28 |
| #30 | 90e1bc0 | Provisional inventory planning, stacked on #29 |
| #31 | 6b9dc41 | CI and runbook, stacked on #30 |

- Candidate d99e12e: SQLite 319 passed, 3 MySQL-only skipped and 1197 assertions. MySQL 322 passed and 1208 assertions. Pint 224 files, Composer platform/audit and 13-operation contract passed. [CI run](https://github.com/MBA-Corp/ATK-Industry/actions/runs/45718696643).
- Prior isolated 34→37 MySQL upgrade, synthetic restore with 37 migrations and 24 permissions, and sales index plan passed. At recovery the development schema had 34 migrations. The three newer migrations add the sales index, expense audit, and inventory planning.
- #26 was a v1.4 business draft. #27 was a separate frontend change.
- Use [testing.md](testing.md) for disposable regression and [deployment.md](deployment.md) for staging operations. Merge to main and production change require separate authorization.
