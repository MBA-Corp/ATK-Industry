<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::query()
            ->with([
                'category:id,name',
                'productUnits.unit:id,code,name',
                'barcodes',
            ]);

        $search = trim(
            (string) $request->query('search', '')
        );

        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query
                    ->where('sku', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhereHas(
                        'barcodes',
                        function ($barcodeQuery) use ($search) {
                            $barcodeQuery->where(
                                'barcode',
                                'like',
                                "%{$search}%"
                            );
                        }
                    );
            });
        }

        if ($request->filled('category_id')) {
            $query->where(
                'category_id',
                $request->integer('category_id')
            );
        }

        if ($request->has('is_active')) {
            $query->where(
                'is_active',
                $request->boolean('is_active')
            );
        }

        $sorts = [
            'name' => ['name', 'asc'],
            '-name' => ['name', 'desc'],
            'sku' => ['sku', 'asc'],
            '-sku' => ['sku', 'desc'],
            'updated_at' => ['updated_at', 'asc'],
            '-updated_at' => ['updated_at', 'desc'],
        ];

        [$sortColumn, $sortDirection] = $sorts[
            (string) $request->query('sort', 'name')
        ] ?? ['name', 'asc'];

        $query->orderBy(
            $sortColumn,
            $sortDirection
        );

        $perPage = min(
            max($request->integer('per_page', 20), 1),
            100
        );

        $products = $query->paginate($perPage);

        return response()->json([
            'data' => ProductResource::collection(
                $products->getCollection()
            )->resolve(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'last_page' => $products->lastPage(),
            ],
        ]);
    }

    public function show(int $productId): ProductResource
    {
        $product = Product::query()
            ->with([
                'category:id,name',
                'productUnits.unit:id,code,name',
                'barcodes',
            ])
            ->findOrFail($productId);

        return new ProductResource($product);
    }
}
