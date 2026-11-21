<?php

namespace App\Actions\Sales;

use Illuminate\Support\Facades\DB;

class RecordManualRefundAction
{
    public function execute(int $returnId, array $data, int $actor): int
    {
        $hash = hash('sha256', json_encode([$returnId, $actor, $data], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($returnId, $data, $actor, $hash) {
            $return = DB::table('sale_returns')->where('id', $returnId)->lockForUpdate()->first();
            abort_if($return === null, 404);
            $existing = DB::table('sale_refunds')->where('sale_return_id', $returnId)->first();
            if ($existing !== null) {
                abort_unless($existing->idempotency_key === $data['idempotency_key']
                    && hash_equals($existing->request_hash, $hash), 409, 'Refund was already recorded.');

                return $existing->id;
            }
            abort_unless((int) $return->refund_due > 0 && (int) $data['amount'] === (int) $return->refund_due,
                422, 'Manual refund must equal the outstanding return amount.');

            return DB::table('sale_refunds')->insertGetId([
                'sale_return_id' => $returnId, 'idempotency_key' => $data['idempotency_key'],
                'request_hash' => $hash, 'amount' => $data['amount'], 'method' => $data['method'],
                'reference' => $data['reference'], 'status' => 'MANUALLY_RECORDED',
                'recorded_by' => $actor, 'recorded_at' => now(),
            ]);
        }, 3);
    }
}
