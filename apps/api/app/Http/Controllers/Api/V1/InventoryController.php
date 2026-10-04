<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\InventoryResource;
use App\Http\Resources\StockMovementResource;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index(
        Request $request
    ): JsonResponse {
        $query = Product::query()
            ->with([
                'inventoryBalance',

                'productUnits' => fn ($query) => $query
                    ->where(
                        'is_base',
                        true
                    )
                    ->with(
                        'unit:id,code,name'
                    ),
            ]);

        if ($request->filled('search')) {
            $search =
                (string) $request->query(
                    'search'
                );

            $query->where(
                function ($query) use (
                    $search
                ) {
                    $query
                        ->where(
                            'sku',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'name',
                            'like',
                            "%{$search}%"
                        );
                }
            );
        }

        if (
            $request->has(
                'is_active'
            )
        ) {
            $query->where(
                'is_active',
                $request->boolean(
                    'is_active'
                )
            );
        }

        $query->orderBy('name');

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

        $products =
            $query->paginate(
                $perPage
            );

        return response()->json([
            'data' => InventoryResource::collection(
                $products
                    ->getCollection()
            )->resolve(),

            'meta' => [
            'current_page' => $products
                ->currentPage(),

            'per_page' => $products
                ->perPage(),

            'total' => $products
                ->total(),

            'last_page' => $products
                ->lastPage(),
            ],
        ]);
    }

    public function show(
        int $productId
    ): InventoryResource {
        $product = Product::query()
            ->with([
                'inventoryBalance',

                'productUnits' => fn ($query) => $query
                    ->where(
                        'is_base',
                        true
                    )
                    ->with(
                        'unit:id,code,name'
                    ),
            ])
            ->findOrFail(
                $productId
            );

        return new InventoryResource(
            $product
        );
    }

    public function movements(
        Request $request,
        int $productId
    ): JsonResponse {
        Product::query()
            ->findOrFail(
                $productId
            );

        $query =
            StockMovement::query()
                ->with([
                    'actor:id,name,username',
                ])
                ->where(
                    'product_id',
                    $productId
                );

        if (
            $request->filled(
                'movement_type'
            )
        ) {
            $query->where(
                'movement_type',
                (string) $request
                    ->query(
                        'movement_type'
                    )
            );
        }

        if (
            $request->filled(
                'date_from'
            )
        ) {
            $query->where(
                'occurred_at',
                '>=',
                $request->query(
                    'date_from'
                )
            );
        }

        if (
            $request->filled(
                'date_to'
            )
        ) {
            $query->where(
                'occurred_at',
                '<=',
                $request->query(
                    'date_to'
                )
            );
        }

        $query
            ->orderByDesc(
                'occurred_at'
            )
            ->orderByDesc('id');

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

        $movements =
            $query->paginate(
                $perPage
            );

        return response()->json([
            'data' => StockMovementResource::collection(
                $movements
                    ->getCollection()
            )->resolve(),

            'meta' => [
            'current_page' => $movements
                ->currentPage(),

            'per_page' => $movements
                ->perPage(),

            'total' => $movements
                ->total(),

            'last_page' => $movements
                ->lastPage(),
            ],
        ]);
    }
}
