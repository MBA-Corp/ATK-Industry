<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function store(StoreProductRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $product = DB::transaction(function () use ($validated) {
                $product = Product::create([
                    'sku' => $validated['sku'],
                    'name' => $validated['name'],
                    'category_id' => $validated['category_id'] ?? null,
                    'size' => $validated['size'] ?? null,
                    'color' => $validated['color'] ?? null,
                    'variant_label' => $validated['variant_label'] ?? null,
                    'is_active' => $validated['is_active'] ?? true,
                ]);

                foreach ($validated['units'] as $unit) {
                    $product->productUnits()->create([
                        'unit_id' => $unit['unit_id'],
                        'conversion_to_base' => $unit['conversion_to_base'],
                        'selling_price' => $unit['selling_price'],
                        'is_base' => $unit['is_base'],
                        'is_active' => $unit['is_active'] ?? true,
                    ]);
                }

                foreach ($validated['barcodes'] ?? [] as $barcode) {
                    $product->barcodes()->create([
                        'barcode' => $barcode['barcode'],
                        'product_unit_id' => null,
                        'is_primary' => $barcode['is_primary'] ?? false,
                    ]);
                }

                return $product->load([
                    'category:id,name',
                    'productUnits.unit:id,code,name',
                    'barcodes',
                ]);
            });
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) === '23000') {
                return response()->json([
                    'message' => 'Product conflicts with existing catalog data.',
                ], 409);
            }

            throw $exception;
        }

        return (new ProductResource($product))
            ->response()
            ->setStatusCode(201);
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
