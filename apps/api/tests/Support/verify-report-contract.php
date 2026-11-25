<?php

use App\Http\Middleware\EnsureActiveUser;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Yaml\Yaml;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$contract = Yaml::parseFile($argv[1] ?? '/var/www/contract/api-contract-v1.0.yaml');
$routes = $app['router']->getRoutes();
$operations = [
    ['POST', 'sales/{saleId}/returns', 'sale.return'],
    ['GET', 'sale-returns/{returnId}', 'sale.return'],
    ['POST', 'sale-returns/{returnId}/refund', 'refund.record'],
    ['GET', 'reports/sales', 'report.sales.view'],
    ['GET', 'reports/sales/export', 'report.sales.view'],
    ['GET', 'reports/profit-loss', 'report.profit.view'],
    ['GET', 'reports/profit-loss/export', 'report.profit.view'],
    ['GET', 'reports/margins/products', 'report.profit.view'],
    ['GET', 'reports/margins/transactions', 'report.profit.view'],
    ['POST', 'expenses', 'expense.manage'],
    ['GET', 'expenses/{expenseId}', 'expense.manage'],
    ['PATCH', 'expenses/{expenseId}', 'expense.manage'],
    ['GET', 'expenses/{expenseId}/history', 'expense.manage'],
    ['GET', 'inventory/{productId}/planning', 'inventory.planning.view'],
    ['PUT', 'inventory/{productId}/planning', 'inventory.planning.manage'],
    ['GET', 'inventory/reorder-alerts', 'inventory.planning.view'],
];

foreach ($operations as [$method, $resource, $permission]) {
    $uri = 'api/v1/'.$resource;
    $operation = $contract['paths']['/'.$uri][strtolower($method)] ?? null;
    $route = collect($routes->getRoutes())->first(
        static fn ($route) => $route->uri() === $uri && in_array($method, $route->methods(), true)
    );
    $middleware = $route?->gatherMiddleware() ?? [];

    if (! isset($operation['responses'][200]) || ! in_array(['sanctum' => []], $operation['security'] ?? [], true)
        || ! in_array('auth:sanctum', $middleware, true)
        || ! in_array(EnsureActiveUser::class, $middleware, true)
        || ! in_array('permission:'.$permission, $middleware, true)) {
        fwrite(STDERR, "Contract or authorization mismatch for $method /$uri\n");
        exit(1);
    }
}

echo 'Reporting, expense, and return route contract verified ('.count($operations)." operations).\n";
