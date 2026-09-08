<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\DB;

// ابحث عن عميل لديه رصيد زائد
$customer = DB::table('customers')->where('balance_syp', '>', 0)->first();
if (!$customer) {
    $customerId = DB::table('customers')->insertGetId([
        'name' => 'عميل تجربة رصيد',
        'phone' => '0999000000',
        'balance_syp' => 10000,
        'balance' => 0,
        'status' => 'دائن',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $customer = DB::table('customers')->find($customerId);
}

// ابحث عن قطعة متوفرة
$part = DB::table('parts')->where('quantity', '>', 0)->where('purchase_price', '>', 0)->first();
if (!$part) {
    $partId = DB::table('parts')->insertGetId([
        'name' => 'قطعة تجربة رصيد',
        'part_number' => 'TEST-CR',
        'quantity' => 100,
        'purchase_price' => 1000,
        'purchase_price_usd' => 0,
        'price' => 1500,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $part = DB::table('parts')->find($partId);
}

echo "عميل: #{$customer->id} ({$customer->name}) - رصيد: {$customer->balance_syp}\n";
echo "قطعة: #{$part->id} ({$part->name}) - مخزون: {$part->quantity} - سعر شراء: {$part->purchase_price}\n";

$request = Request::create('/invoices', 'POST', [
    'customer_id' => $customer->id,
    'sale_date' => now()->toDateString(),
    'paid' => 0,
    'settle_credit' => 4500,
    'currency' => 'SYP',
    'notes' => 'اختبار تسديد رصيد زائد',
    'items' => [
        ['part_id' => $part->id, 'quantity' => 1, 'unit_price' => 4500],
    ],
]);

$controller = new InvoiceController();
$response = $controller->store($request);

if ($response->getStatusCode() >= 400) {
    echo "\nخطأ: " . $response->getContent() . "\n";
    exit(1);
}

$data = json_decode($response->getContent(), true);
$invoiceId = $data['id'] ?? null;
echo "\nتم إنشاء فاتورة #{$invoiceId}\n";
echo "الإجمالي: {$data['total']}\n";
echo "مدفوع: {$data['paid']}\n";
echo "رصيد مستخدم: " . ($data['credit_used'] ?? 'غير موجود') . "\n";
echo "متبقي: {$data['remaining']}\n";
echo "حالة: {$data['status']}\n";

$freshCustomer = DB::table('customers')->find($customer->id);
echo "\nرصيد العميل بعد: {$freshCustomer->balance_syp}\n";
