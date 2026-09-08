<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Purchase;
use App\Models\Supplier;

$purchase = Purchase::find(8);
$supplier = Supplier::find($purchase->supplier_id);

$currency = $purchase->currency;
$field = $currency === 'USD' ? 'balance_usd' : 'balance_syp';

// احسب الرصيد الزائد المتاح قبل هذه الفاتورة
$beforeBalance = $supplier->{$field};
$over = max(0, -$beforeBalance); // لكن before غير متوفر بعد التغيير، نستنتجه من الفرق

// الفرق بين المتبقي الحالي والمتبقي بعد خصم الرصيد
$credit = min($purchase->remaining, max(0, -($beforeBalance - $purchase->remaining)));

// نطبق 450$ من الرصيد الزائد على فاتورة 8
$credit = min($purchase->remaining, 450);

DB::transaction(function () use ($purchase, $supplier, $field, $credit) {
    $newRemaining = round($purchase->remaining - $credit, 2);
    $purchase->credit_used = round($credit, 2);
    $purchase->remaining = $newRemaining;
    $purchase->save();

    $supplier->{$field} = round($supplier->{$field} - $credit, 2);
    $supplier->status = $supplier->balance_syp > 0 || $supplier->balance_usd > 0
        ? 'علينا'
        : ($supplier->balance_syp < 0 || $supplier->balance_usd < 0 ? 'لنا' : 'متوازن');
    $supplier->save();
});

echo "Fixed purchase #{$purchase->id}: credit_used={$purchase->credit_used}, remaining={$purchase->remaining}, supplier {$field}={$supplier->{$field}}\n";
