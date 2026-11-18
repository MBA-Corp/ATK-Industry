<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Sales\RecordManualRefundAction;
use App\Actions\Sales\ReturnSaleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSaleReturnRequest;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleReturnController extends Controller
{
    public function store(StoreSaleReturnRequest $request, int $saleId, ReturnSaleAction $action): JsonResponse
    {
        try {
            $id = $action->execute($saleId, $request->validated(), $request->user()->id);

            return $this->show($id);
        } catch (UniqueConstraintViolationException) {
            return response()->json(['message' => 'Concurrent return request conflicts; retry with the same key.'], 409);
        }
    }

    public function show(int $returnId): JsonResponse
    {
        $return = DB::table('sale_returns')->where('id', $returnId)->first([
            'id', 'sale_id', 'actor_id', 'replacement_sale_id', 'reason', 'refund_due', 'returned_at',
        ]);
        abort_if($return === null, 404);
        $return->original_sale_number = DB::table('sales')->where('id', $return->sale_id)->value('sale_number');
        $return->items = DB::table('sale_return_items as ri')->join('sale_items as si', 'si.id', '=', 'ri.sale_item_id')
            ->where('ri.sale_return_id', $returnId)->orderBy('ri.id')
            ->get(['ri.id', 'ri.sale_item_id', 'ri.quantity', 'ri.quantity_base', 'ri.condition',
                'ri.refund_amount', 'si.product_sku', 'si.product_name', 'si.unit_code']);
        $return->refund = DB::table('sale_refunds')->where('sale_return_id', $returnId)->first([
            'id', 'amount', 'method', 'reference', 'status', 'recorded_by', 'recorded_at',
        ]);
        $return->refund_status = $return->refund !== null ? 'MANUALLY_RECORDED'
            : ((int) $return->refund_due === 0 ? 'NOT_DUE' : 'AWAITING_MANUAL_RECORD');

        return response()->json(['data' => $return]);
    }

    public function refund(Request $request, int $returnId, RecordManualRefundAction $action): JsonResponse
    {
        $data = $request->validate([
            'idempotency_key' => 'required|uuid', 'amount' => 'required|integer|min:1',
            'method' => 'required|in:CASH,QRIS,BANK_TRANSFER,OTHER',
            'reference' => 'required|string|max:255', 'confirm_record_only' => 'required|accepted',
        ]);
        try {
            $action->execute($returnId, $data, $request->user()->id);

            return $this->show($returnId);
        } catch (UniqueConstraintViolationException) {
            return response()->json(['message' => 'Refund idempotency conflict.'], 409);
        }
    }
}
