# Accounting policy boundaries

Status: Pending business validation. No financial go-live approval.

- Recommended technical method: perpetual Moving Weighted Average in base units.
- CD-002 excludes freight from product HPP; the supplied periodic example includes
  freight in net purchases. These are different policies, not interchangeable facts.
- Initial operational candidate excludes freight from inventory valuation and records
  it separately. Do not label candidate figures official profit until approved.
- Unknown opening cost is unknown, never zero or inferred from current selling price.
- Capture sale cost at sale time; never recompute historical profit from current prices.
- Return restock reverses the original cost snapshot; damaged goods do not increase
  sellable stock. Manual refund status records operator evidence, not gateway success.
- Periodic HPP and perpetual COGS are reconciliation views, never additive expenses.
- Supplied periodic example is synthetic test data: purchases net 5,050,000; HPP
  10,050,000; net sales 15,700,000; gross 5,650,000; simple net 3,950,000 rupiah.
- Taxes, interest and other accounting adjustments are outside simple net profit.
