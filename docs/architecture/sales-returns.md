# Sales returns and manual refund contract

Active authentication plus sale.return permission:
- POST /api/v1/sales/{saleId}/returns
- GET /api/v1/sale-returns/{returnId} (receipt/detail, original sale number and item snapshots)

Create JSON: idempotency_key UUID, reason (max 255), optional replacement_sale_id,
items [{sale_item_id, quantity (decimal string, six places), condition:
resellable|damaged}]. Up to 200 distinct lines. Replacement is a different completed
sale reference only; the API does not net payments or create an exchange sale.
POST responds 200 with the return detail, including on identical replay.
Changed payload under an existing key returns 409. Exact payload/actor replay is
required; no claim of semantic equivalence for differently ordered requests.

Only completed non-cancelled sales may be returned. Sale and item locks serialize
requests; cumulative returned quantity cannot exceed original quantity.
Partial and full returns keep the original sale/items/payment history intact.
Full cancellation is rejected after any return to prevent double stock reversal.

Refund and stock allocation use cumulative proportions of the original immutable
line total and stock ledger. Rounding is allocated across returns so total entitlement
never exceeds the original amount. Resellable return quantity must be representable
at six-decimal base-unit precision; subprecision splits are rejected.
Resellable stock restores its original allocated sale cost, not today's average.
Damaged goods do not enter available stock or reverse COGS.
Unknown original costs remain unknown.

Detail fields include refund_due, refund_status, items, actor_id, returned_at,
original_sale_number and optional replacement_sale_id. Internal cost/hash fields are
not returned to the return operator. Status is AWAITING_MANUAL_RECORD, NOT_DUE or
MANUALLY_RECORDED.

Permission refund.record:
POST /api/v1/sale-returns/{returnId}/refund with idempotency_key, amount (integer rupiah),
method CASH|QRIS|BANK_TRANSFER|OTHER, reference (operator evidence, max 255),
confirm_record_only=true. Exactly one full-amount manual record per return.
Exact replay returns same record; different/double recording returns 409.
Partial refund disbursements and gateway execution are not implemented.
MANUALLY_RECORDED means an operator recorded a payout, NOT verified bank/gateway success.
No external payment is initiated. Original sale payment records are never overwritten.
