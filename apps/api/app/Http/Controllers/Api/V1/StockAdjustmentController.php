<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Inventory\CreateStockAdjustmentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStockAdjustmentRequest;
use App\Http\Resources\StockAdjustmentResource;
use Illuminate\Http\JsonResponse;

class StockAdjustmentController extends Controller
{
    public function store(
        StoreStockAdjustmentRequest $request,
        int $productId,
        CreateStockAdjustmentAction $action
    ): JsonResponse {
        $adjustment = $action->execute(
            $productId,
            $request->validated(),
            $request->user()?->id
        );

        return (new StockAdjustmentResource(
            $adjustment
        ))
            ->response()
            ->setStatusCode(201);
    }
}
