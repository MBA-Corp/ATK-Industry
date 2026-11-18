<?php

use App\Actions\Sales\RecordManualRefundAction;
use App\Actions\Sales\ReturnSaleAction;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.connections.mysql.host') !== 'mysql-test'
    || config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'atk_industry_test'
    || config('database.connections.mysql.username') !== 'atk_test' || config('database.connections.mysql.url')) {
    exit(3);
}
try {
    $data = json_decode($argv[3], true, flags: JSON_THROW_ON_ERROR);
    if ($argv[1] === 'return') {
        app(ReturnSaleAction::class)->execute((int) $argv[2], $data, (int) $argv[4]);
    } else {
        app(RecordManualRefundAction::class)->execute((int) $argv[2], $data, (int) $argv[4]);
    }
    echo "accepted\n";
} catch (ValidationException|HttpException) {
    exit(2);
}
