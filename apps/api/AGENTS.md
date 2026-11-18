# ATK backend engineering

Work in apps/api; do not edit or run apps/web without explicit user instruction.
Run PHP and Composer via Docker. Laravel Boost is installed as a development dependency;
do not reinstall it or install host PHP as routine bootstrap.

- Read the current working tree before edits; preserve user changes.
- Keep controllers thin and business operations in Actions.
- Use database transactions, deterministic row locks and unique source references.
- Money is integer rupiah; quantities are DECIMAL(18,6) strings. Use MoneyMath,
  DecimalMath or BCMath, never float arithmetic for monetary/inventory state.
- Do not modify applied historical migrations. Add migrations.
- New features require authorization, validation, regression tests and API documentation.
- Run SQLite and isolated MySQL tests with compose.test.yaml; never point RefreshDatabase
  at development or production. Do not delete existing database volumes.
- Run Pint and Composer audit/platform checks before committing.
- Document unresolved accounting policy; do not report provisional costs as final accounts.
- No real client spreadsheets, documents, credentials or .env files in Git.
- Feature branches and PRs are authorized; production deployment is not. Do not merge
  while deployment automation or business approval remains unresolved.
- Maintain docs/backend-roadmap.md and the existing docs/architecture API contracts.
