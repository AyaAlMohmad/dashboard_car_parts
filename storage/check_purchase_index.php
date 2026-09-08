<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\PurchaseController;
use Illuminate\Http\Request;

$controller = new PurchaseController();
$request = Request::create('/purchases');
$response = $controller->index($request);
$data = $response->getData(true);

foreach ($data['data'] as $p) {
    if ($p['id'] <= 10) {
        echo "#{$p['id']} paid={$p['paid']} credit_used={$p['credit_used']} remaining={$p['remaining']} total={$p['total']} currency={$p['currency']}\n";
    }
}
