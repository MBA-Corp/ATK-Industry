<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Sales\CancelSaleAction;
use App\Actions\Sales\CreateSaleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelSaleRequest;
use App\Http\Requests\StoreSaleRequest;
use App\Http\Resources\SaleResource;
use App\Models\Sale;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function index(
        Request $request
    ): JsonResponse {
        $query = Sale::query()
            ->with([
                'terminal:id,terminal_uuid,name',
                'cashier:id,name,username',
            ]);

        if ($request->filled('status')) {
            $query->where(
                'status',
                (string) $request->query('status')
            );
        }

        if ($request->filled('terminal_id')) {
            $query->where(
                'terminal_id',
                $request->integer('terminal_id')
            );
        }

        if ($request->filled('cashier_user_id')) {
            $query->where(
                'cashier_user_id',
                $request->integer(
                    'cashier_user_id'
                )
            );
        }

        if ($request->filled('date_from')) {
            $query->whereDate(
                'sold_at',
                '>=',
                $request->query('date_from')
            );
        }

        if ($request->filled('date_to')) {
            $query->whereDate(
                'sold_at',
                '<=',
                $request->query('date_to')
            );
        }

        if ($request->filled('search')) {
            $search = trim(
                (string) $request->query('search')
            );

            $query->where(
                'sale_number',
                'like',
                "%{$search}%"
            );
        }

        $query->orderByDesc('sold_at');

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

        $sales = $query->paginate(
            $perPage
        );

        return response()->json([
            'data' => SaleResource::collection(
                $sales->getCollection()
            )->resolve(),

            'meta' => [
                'current_page' => $sales
                    ->currentPage(),

                'per_page' => $sales
                    ->perPage(),

                'total' => $sales->total(),

                'last_page' => $sales
                    ->lastPage(),
            ],
        ]);
    }

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

    public function show(
        int $saleId
    ): SaleResource {
        $sale = Sale::query()
            ->with([
                'terminal:id,terminal_uuid,name',
                'cashier:id,name,username',
                'items',
                'payments',
                'cancellation.cancelledBy:id,name,username',
            ])
            ->findOrFail($saleId);

        return new SaleResource(
            $sale
        );
    }

    public function cancel(
        CancelSaleRequest $request,
        int $saleId,
        CancelSaleAction $action
    ): SaleResource {
        $sale = $action->execute(
            $saleId,
            $request->validated(),
            $request->user()->id
        );

        return new SaleResource(
            $sale
        );
    }
}
