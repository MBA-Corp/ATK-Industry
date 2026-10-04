<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(
        Request $request
    ): JsonResponse {
        $query = Supplier::query();

        $search = trim(
            (string) $request->query('search', '')
        );

        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query
                    ->where(
                        'code',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'name',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'city',
                        'like',
                        "%{$search}%"
                    );
            });
        }

        if ($request->has('is_active')) {
            $query->where(
                'is_active',
                $request->boolean('is_active')
            );
        }

        $query
            ->orderBy('name')
            ->orderBy('code');

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

        $suppliers = $query->paginate(
            $perPage
        );

        return response()->json([
            'data' => SupplierResource::collection(
                $suppliers->getCollection()
            )->resolve(),

            'meta' => [
                'current_page' => $suppliers->currentPage(),

                'per_page' => $suppliers->perPage(),

                'total' => $suppliers->total(),

                'last_page' => $suppliers->lastPage(),
            ],
        ]);
    }

    public function store(
        StoreSupplierRequest $request
    ): JsonResponse {
        try {
            $supplier = Supplier::create(
                $request->validated()
            );
        } catch (QueryException $exception) {
            if (
                ($exception->errorInfo[0] ?? null)
                === '23000'
            ) {
                return response()->json([
                    'message' => 'Supplier conflicts with existing data.',
                ], 409);
            }

            throw $exception;
        }

        return (new SupplierResource(
            $supplier
        ))
            ->response()
            ->setStatusCode(201);
    }

    public function show(
        int $supplierId
    ): SupplierResource {
        return new SupplierResource(
            Supplier::query()
                ->findOrFail($supplierId)
        );
    }

    public function update(
        UpdateSupplierRequest $request,
        int $supplierId
    ): SupplierResource|JsonResponse {
        $supplier = Supplier::query()
            ->findOrFail($supplierId);

        try {
            $supplier->update(
                $request->validated()
            );
        } catch (QueryException $exception) {
            if (
                ($exception->errorInfo[0] ?? null)
                === '23000'
            ) {
                return response()->json([
                    'message' => 'Supplier conflicts with existing data.',
                ], 409);
            }

            throw $exception;
        }

        return new SupplierResource(
            $supplier->refresh()
        );
    }
}
