<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\DB;

$customerId = DB::table('customers')->insertGetId([
    'name' => 'عميل اختبار استرجاع',
    'phone' => '0999222222',
    'balance' => 0,
    'balance_syp' => 100,
    'balance_usd' => 0,
    'status' => 'دائن',
    'created_at' => now(),
    'updated_at' => now(),
]);

echo "قبل: " . DB::table('customers')->find($customerId)->balance_syp . "\n";

$request = Request::create('/payments/refund', 'POST', [
    'customer_id' => $customerId,
    'amount' => 30,
    'currency' => 'SYP',
    'payment_date' => now()->toDateString(),
    'notes' => '',
]);

$controller = new PaymentController();
$response = $controller->refund($request);

if ($response->getStatusCode() >= 400) {
    echo "خطأ: " . $response->getContent() . "\n";
    exit(1);
}

echo "بعد: " . DB::table('customers')->find($customerId)->balance_syp . "\n";
