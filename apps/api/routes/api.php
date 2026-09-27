<?php

use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\TerminalController;
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
        Route::get('/me', [AuthController::class, 'me']);

        Route::get('/products', [ProductController::class, 'index']);

        Route::post(
            '/products',
            [ProductController::class, 'store']
        )->middleware('permission:product.create');

        Route::get('/products/{productId}', [
            ProductController::class,
            'show',
        ]);
        Route::get('/terminals', [TerminalController::class, 'index']);
        Route::post('/terminals', [TerminalController::class, 'store']);
    });
});
