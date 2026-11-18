# Backend release roadmap

Status vocabulary: Implemented = code exists; Verified = observed tests; Proposed =
not implemented; Pending business validation = cannot be used as official accounts.

## Baseline — 18 November 2026
- Upstream main b1d0c8f; 31 historical migrations.
- PHP 8.3, MySQL 8.4, Laravel 13; original suite 286 tests / 971 assertions.
- Existing: catalog, RBAC, purchasing, one-shot receiving, inventory ledger,
  adjustments, checkout/payments, cancellation, receipts, invoice and company profile.
- No CR-01–08 complete implementation found. No original XLSX/PDF found in repository
  or current Codex workspace. Use synthetic fixtures only.
- Upstream account access is read-only. Fork PR workflow; never push main.
- No GitHub Actions workflows listed. Webhook/admin deployment visibility unavailable;
  external auto-deployment remains unknown. Merge/deployment requires resolution.

## Milestones
1. Foundation hardening — Implemented / Verified: dependency security patches, inactive-session
   rejection, Docker development setup, isolated MySQL/SQLite runner, formatting.
2. Catalog import and valuation foundations — Proposed.
3. Jakarta date ranges and sales/purchase/product reports — Proposed.
4. Partial returns, manual refunds, reversible cost snapshots — Proposed.
5. Provisional margin, HPP and operating expenses — Proposed.
6. Statistical safety stock, ROP, maximum stock — Proposed.
7. Release regression, concurrency, security and operational review — Proposed.

Dependencies: reports must reconcile returns; valuation hooks must cover all stock
movements before any financial result can be considered complete. Opening valuation
for legacy stock must never be guessed. The importer must preview before committing.
Production readiness also requires backup/restore rehearsal, monitoring and UAT.

## Initial audit findings
- Composer audit reported eight advisories across five packages; targeted updates
  selected Laravel 13.43.0 and patched Symfony components; subsequent audit clean.
- Login rejects inactive users, but existing authenticated sessions/tokens were not
  rechecked. Regression coverage and middleware added.
- Pint found array indentation in InventoryController; fixed.
- Existing money/quantity actions use transactions and precision helpers. Float
  acceptance at legacy action boundaries, inconsistent list date validation, and
  concurrency/deadlock behavior need focused review.
- Existing receiving is single receipt per purchase; partial/repeated receipt support
  must not be claimed or added implicitly.

## Foundation verification
SQLite: 288 tests / 975 assertions PASS. MySQL: 288 tests / 975 assertions PASS.
Pint: 181 files PASS after import formatting. Platform checks PASS. Composer audit: zero advisories.
Production deployment and accounting approval remain pending; this is not a production-readiness claim.
