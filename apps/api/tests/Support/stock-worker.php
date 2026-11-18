<?php

use App\Actions\Inventory\ApplyStockMovementAction;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'mysql'
    || config('database.connections.mysql.host') !== 'mysql-test'
    || config('database.connections.mysql.database') !== 'atk_industry_test'
    || config('database.connections.mysql.username') !== 'atk_test'
    || config('database.connections.mysql.url')) {
    fwrite(STDERR, "Unsafe concurrent-test database.\n");
    exit(3);
}
try {
    app(ApplyStockMovementAction::class)->execute(
        (int) $argv[1], 'SALE', 'CONCURRENT_TEST', (int) $argv[2], '-4',
    );
    echo "accepted\n";
} catch (ValidationException) {
    echo "rejected\n";
    exit(2);
}
