<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Purchasing\CreatePurchaseAction;
use App\Actions\Purchasing\ReceivePurchaseAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReceivePurchaseRequest;
use App\Http\Requests\StorePurchaseRequest;
use App\Http\Resources\PurchaseResource;
use App\Models\Purchase;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    public function index(
        Request $request
    ): JsonResponse {
        $query = Purchase::query()
            ->with([
                'supplier:id,code,name',
                'createdBy:id,name,username',
            ]);

        if ($request->filled('supplier_id')) {
            $query->where(
                'supplier_id',
                $request->integer(
                    'supplier_id'
                )
            );
        }

        if ($request->filled('status')) {
            $query->where(
                'status',
                (string) $request->query(
                    'status'
                )
            );
        }

        if ($request->filled('date_from')) {
            $query->whereDate(
                'purchase_date',
                '>=',
                $request->query('date_from')
            );
        }

        if ($request->filled('date_to')) {
            $query->whereDate(
                'purchase_date',
                '<=',
                $request->query('date_to')
            );
        }

        $query->orderByDesc(
            'purchase_date'
        );

        $perPage = min(
            max(
                $request->integer(
                    'per_page',
                    20
                ),
                1
            ),
            100
        );

        $purchases = $query->paginate(
            $perPage
        );

        return response()->json([
            'data' => PurchaseResource::collection(
                $purchases
                    ->getCollection()
            )->resolve(),

            'meta' => [
                'current_page' => $purchases->currentPage(),

                'per_page' => $purchases->perPage(),

                'total' => $purchases->total(),

                'last_page' => $purchases->lastPage(),
            ],
        ]);
    }

    public function store(
        StorePurchaseRequest $request,
        CreatePurchaseAction $action
    ): JsonResponse {
        try {
            $purchase = $action->execute(
                $request->validated(),
                $request->user()?->id
            );
        } catch (QueryException $exception) {
            if (
                ($exception->errorInfo[0] ?? null)
                === '23000'
            ) {
                return response()->json([
                    'message' => 'Purchase conflicts with existing data.',
                ], 409);
            }

            throw $exception;
        }

        return (new PurchaseResource(
            $purchase
        ))
            ->response()
            ->setStatusCode(201);
    }

    public function show(
        int $purchaseId
    ): PurchaseResource {
        $purchase = Purchase::query()
            ->with([
                'supplier:id,code,name',
                'createdBy:id,name,username',

                'items.productSupplier.product:id,sku,name',

                'items.productUnit.unit:id,code,name',
            ])
            ->findOrFail($purchaseId);

        return new PurchaseResource(
            $purchase
        );
    }

    public function receive(
        ReceivePurchaseRequest $request,
        int $purchaseId,
        ReceivePurchaseAction $action
    ): PurchaseResource {
        $purchase = $action->execute(
            $purchaseId,
            $request->validated(),
            $request->user()?->id
        );

        return new PurchaseResource(
            $purchase
        );
    }
}
