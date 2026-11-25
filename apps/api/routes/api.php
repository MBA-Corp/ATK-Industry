<?php

use App\Http\Controllers\Api\V1\CatalogImportController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CompanyProfileController;
use App\Http\Controllers\Api\V1\FinancialReportController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\OperatingExpenseController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductSupplierController;
use App\Http\Controllers\Api\V1\PurchaseController;
use App\Http\Controllers\Api\V1\PurchaseInvoiceController;
use App\Http\Controllers\Api\V1\SaleController;
use App\Http\Controllers\Api\V1\SaleReturnController;
use App\Http\Controllers\Api\V1\SalesReportController;
use App\Http\Controllers\Api\V1\StockAdjustmentController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\TerminalController;
use App\Http\Controllers\Api\V1\UnitController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Middleware\EnsureActiveUser;
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

    Route::middleware(['auth:sanctum', EnsureActiveUser::class])->group(function () {
        Route::middleware('permission:report.profit.view')->group(function () {
            Route::get('/reports/profit-loss', [FinancialReportController::class, 'show']);
            Route::get('/reports/profit-loss/export', [FinancialReportController::class, 'export']);
            Route::get('/reports/margins/products', [FinancialReportController::class, 'products']);
            Route::get('/reports/margins/transactions', [FinancialReportController::class, 'transactions']);
        });
        Route::middleware('permission:expense.manage')->group(function () {
            Route::post('/expenses', [OperatingExpenseController::class, 'store']);
            Route::get('/expenses/{expenseId}', [OperatingExpenseController::class, 'show'])->whereNumber('expenseId');
            Route::patch('/expenses/{expenseId}', [OperatingExpenseController::class, 'update'])->whereNumber('expenseId');
            Route::get('/expenses/{expenseId}/history', [OperatingExpenseController::class, 'history'])->whereNumber('expenseId');
        });
        Route::get('/reports/sales', [SalesReportController::class, 'index'])->middleware('permission:report.sales.view');
        Route::get('/reports/sales/export', [SalesReportController::class, 'export'])->middleware('permission:report.sales.view');
        Route::post('/sales/{saleId}/returns', [SaleReturnController::class, 'store'])->middleware('permission:sale.return');
        Route::get('/sale-returns/{returnId}', [SaleReturnController::class, 'show'])->middleware('permission:sale.return');
        Route::post('/sale-returns/{returnId}/refund', [SaleReturnController::class, 'refund'])->middleware('permission:refund.record');
        Route::post('/catalog-imports/preview', [CatalogImportController::class, 'preview'])->middleware('permission:catalog.import');
        Route::post('/catalog-imports/commit', [CatalogImportController::class, 'commit'])->middleware('permission:catalog.import');
        Route::get(
            '/purchases/{purchaseId}/invoice',
            [PurchaseInvoiceController::class, 'show']
        )->middleware('permission:purchase.invoice.view');

        Route::get(
            '/purchases/{purchaseId}/invoice/download',
            [PurchaseInvoiceController::class, 'download']
        )->middleware('permission:purchase.invoice.view');

        Route::post(
            '/purchases/{purchaseId}/invoice',
            [PurchaseInvoiceController::class, 'store']
        )->middleware('permission:purchase.invoice.manage');

        Route::get(
            '/company-profile',
            [CompanyProfileController::class, 'show']
        );

        Route::put(
            '/company-profile',
            [CompanyProfileController::class, 'update']
        )->middleware(
            'permission:company_profile.update'
        );
        Route::get(
            '/sales/{saleId}/receipt',
            [SaleController::class, 'receipt']
        );
        Route::post(
            '/sales/{saleId}/cancel',
            [SaleController::class, 'cancel']
        )->middleware(
            'permission:sale.cancel'
        );
        Route::get(
            '/sales',
            [SaleController::class, 'index']
        );

        Route::post(
            '/sales',
            [SaleController::class, 'store']
        )->middleware(
            'permission:sale.create'
        );

        Route::get(
            '/sales/{saleId}',
            [SaleController::class, 'show']
        );
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
