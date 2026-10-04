<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Purchasing\CreateProductSupplierAction;
use App\Actions\Purchasing\UpdateProductSupplierAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductSupplierRequest;
use App\Http\Requests\UpdateProductSupplierRequest;
use App\Http\Resources\ProductSupplierResource;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;

class ProductSupplierController extends Controller
{
    public function index(
        int $productId
    ): JsonResponse {
        $product = Product::query()
            ->findOrFail($productId);

        $productSuppliers = $product
            ->productSuppliers()
            ->with(
                'supplier:id,code,name,is_active'
            )
            ->orderByDesc('is_preferred')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => ProductSupplierResource::collection(
                $productSuppliers
            )->resolve(),
        ]);
    }

    public function store(
        StoreProductSupplierRequest $request,
        int $productId,
        CreateProductSupplierAction $action
    ): JsonResponse {
        try {
            $productSupplier = $action->execute(
                $productId,
                $request->validated(),
                $request->user()?->id
            );
        } catch (QueryException $exception) {
            if (
                ($exception->errorInfo[0] ?? null)
                === '23000'
            ) {
                return response()->json([
                    'message' => 'Product supplier conflicts with existing data.',
                ], 409);
            }

            throw $exception;
        }

        return (new ProductSupplierResource(
            $productSupplier
        ))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateProductSupplierRequest $request,
        int $productId,
        int $productSupplierId,
        UpdateProductSupplierAction $action
    ): ProductSupplierResource|JsonResponse {
        try {
            $productSupplier = $action->execute(
                $productId,
                $productSupplierId,
                $request->validated(),
                $request->user()?->id
            );
        } catch (QueryException $exception) {
            if (
                ($exception->errorInfo[0] ?? null)
                === '23000'
            ) {
                return response()->json([
                    'message' => 'Product supplier conflicts with existing data.',
                ], 409);
            }

            throw $exception;
        }

        return new ProductSupplierResource(
            $productSupplier
        );
    }
}
