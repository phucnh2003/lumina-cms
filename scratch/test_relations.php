<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use Illuminate\Contracts\Console\Kernel;
use Lumina\Payment\Models\Transaction;

$result = Transaction::applyQuery([
    'fields' => ['id', 'transaction_number', 'order.order_number'],
]);

print_r($result->toArray());
