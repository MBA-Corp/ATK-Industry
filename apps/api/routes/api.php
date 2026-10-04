<?php

use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductSupplierController;
use App\Http\Controllers\Api\V1\PurchaseController;
use App\Http\Controllers\Api\V1\SaleController;
use App\Http\Controllers\Api\V1\StockAdjustmentController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\TerminalController;
use App\Http\Controllers\Api\V1\UnitController;
use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', function () {
        return response()->json([
            'data' => [
                'status' => 'ok',
                'service' => 'atk-industry-api',
            ],
        ]);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post(
            '/sales',
            [SaleController::class, 'store']
        )->middleware('permission:sale.create');
        Route::post(
            '/inventory/{productId}/adjustments',
            [StockAdjustmentController::class, 'store']
        )->middleware(
            'permission:inventory.adjust'
        );
        Route::get(
            '/inventory',
            [InventoryController::class, 'index']
        );

        Route::get(
            '/inventory/{productId}',
            [InventoryController::class, 'show']
        );

        Route::get(
            '/inventory/{productId}/movements',
            [InventoryController::class, 'movements']
        );
        Route::get(
            '/purchases',
            [PurchaseController::class, 'index']
        );

        Route::post(
            '/purchases',
            [PurchaseController::class, 'store']
        )->middleware('permission:purchase.create');
        Route::post(
            '/purchases/{purchaseId}/receive',
            [PurchaseController::class, 'receive']
        )->middleware('permission:purchase.receive');

        Route::get(
            '/purchases/{purchaseId}',
            [PurchaseController::class, 'show']
        );
        Route::get(
            '/products/{productId}/suppliers',
            [ProductSupplierController::class, 'index']
        );

        Route::post(
            '/products/{productId}/suppliers',
            [ProductSupplierController::class, 'store']
        )->middleware('permission:product.update');

        Route::patch(
            '/products/{productId}/suppliers/{productSupplierId}',
            [ProductSupplierController::class, 'update']
        )->middleware('permission:product.update');
        Route::get(
            '/suppliers',
            [SupplierController::class, 'index']
        );

        Route::post(
            '/suppliers',
            [SupplierController::class, 'store']
        )->middleware('permission:supplier.create');

        Route::get(
            '/suppliers/{supplierId}',
            [SupplierController::class, 'show']
        );

        Route::patch(
            '/suppliers/{supplierId}',
            [SupplierController::class, 'update']
        )->middleware('permission:supplier.update');
        Route::get('/me', [AuthController::class, 'me']);
        Route::get(
            '/categories',
            [CategoryController::class, 'index']
        );

        Route::post(
            '/categories',
            [CategoryController::class, 'store']
        )->middleware('permission:category.create');

        Route::patch(
            '/categories/{categoryId}',
            [CategoryController::class, 'update']
        )->middleware('permission:category.update');

        Route::get(
            '/units',
            [UnitController::class, 'index']
        );

        Route::post(
            '/units',
            [UnitController::class, 'store']
        )->middleware('permission:unit.create');

        Route::patch(
            '/units/{unitId}',
            [UnitController::class, 'update']
        )->middleware('permission:unit.update');

        Route::get('/products', [ProductController::class, 'index']);

        Route::post(
            '/products',
            [ProductController::class, 'store']
        )->middleware('permission:product.create');

        Route::patch(
            '/products/{productId}',
            [ProductController::class, 'update']
        )->middleware('permission:product.update');

        Route::get('/products/{productId}', [
            ProductController::class,
            'show',
        ]);
        Route::get('/terminals', [TerminalController::class, 'index']);
        Route::post('/terminals', [TerminalController::class, 'store']);
    });
});
