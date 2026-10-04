<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Sales\CreateSaleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSaleRequest;
use App\Http\Resources\SaleResource;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;

class SaleController extends Controller
{
    public function store(
        StoreSaleRequest $request,
        CreateSaleAction $action
    ): JsonResponse {
        try {
            $sale = $action->execute(
                $request->validated(),
                $request->user()->id
            );
        } catch (QueryException $exception) {
            if (
                ($exception->errorInfo[0] ?? null)
                === '23000'
            ) {
                return response()->json([
                    'message' => 'Sale conflicts with existing data.',
                ], 409);
            }

            throw $exception;
        }

        return (new SaleResource(
            $sale
        ))
            ->response()
            ->setStatusCode(201);
    }
}
