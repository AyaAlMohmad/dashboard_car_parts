<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use App\Http\Controllers\PurchaseController;
use Illuminate\Support\Facades\DB;

// مورد برصيد زائد 100 دولار (لنا)
$supplierId = DB::table('suppliers')->insertGetId([
    'name' => 'مورد تجربة رصيد',
    'phone' => '0999111111',
    'balance_syp' => 0,
    'balance_usd' => -100,
    'balance' => 0,
    'status' => 'لنا',
    'created_at' => now(),
    'updated_at' => now(),
]);

$categoryId = DB::table('categories')->insertGetId([
    'name' => 'غير مصنف',
    'created_at' => now(),
    'updated_at' => now(),
]);

$partNumber = 'TEST-PUR-CR-' . uniqid();
$partId = DB::table('parts')->insertGetId([
    'name' => 'قطعة شراء تجربة',
    'part_number' => $partNumber,
    'category_id' => $categoryId,
    'quantity' => 0,
    'purchase_price' => 0,
    'purchase_price_usd' => 100,
    'sale_price' => 0,
    'sale_price_usd' => 0,
    'created_at' => now(),
    'updated_at' => now(),
]);

echo "مورد: #{$supplierId} - رصيد USD: " . DB::table('suppliers')->find($supplierId)->balance_usd . "\n";
echo "قطعة: #{$partId} - سعر شراء USD: 100\n";

$request = Request::create('/purchases', 'POST', [
    'supplier_id' => $supplierId,
    'purchase_date' => now()->toDateString(),
    'paid' => 0,
    'settle_credit' => 100,
    'currency' => 'USD',
    'notes' => 'اختبار رصيد زائد',
    'items' => [
        ['part_id' => $partId, 'part_name' => null, 'quantity' => 1, 'unit_price' => 100],
    ],
]);

$controller = new PurchaseController();
$response = $controller->store($request);

if ($response->getStatusCode() >= 400) {
    echo "\nخطأ: " . $response->getContent() . "\n";
    exit(1);
}

echo "\nتم إنشاء الشراء ✅\n";

$rows = DB::table('purchases')->where('supplier_id', $supplierId)->get();
foreach ($rows as $p) {
    $part = DB::table('parts')->find($p->part_id);
    $isOldDebt = $part && $part->part_number === 'OLD_DEBT';
    echo "شراء #{$p->id}: " . ($isOldDebt ? 'OLD_DEBT' : 'item') . " total={$p->total} paid={$p->paid} credit_used={$p->credit_used} remaining={$p->remaining} currency={$p->currency}\n";
}

$freshSupplier = DB::table('suppliers')->find($supplierId);
echo "\nرصيد المورد بعد: USD {$freshSupplier->balance_usd}\n";
