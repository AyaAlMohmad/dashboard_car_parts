<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$issues = [];
$checks = 0;

function r($n) { return round((float) $n, 2); }

echo "=== التحقق من الفواتير ===\n";
$invoices = DB::table('invoices')->get();
foreach ($invoices as $inv) {
    $itemsTotal = DB::table('invoice_items')->where('invoice_id', $inv->id)->sum(DB::raw('unit_price * quantity'));
    if (r($itemsTotal) !== r($inv->total)) {
        $issues[] = "فاتورة #{$inv->id}: المجموع من items " . r($itemsTotal) . " لا يساوي total {$inv->total}";
    }
    $expectedRemaining = r(max(0, $inv->total - $inv->paid - $inv->credit_used));
    if (r($inv->remaining) !== $expectedRemaining) {
        $issues[] = "فاتورة #{$inv->id}: المتبقي {$inv->remaining} لا يساوي المتوقع {$expectedRemaining}";
    }
    $checks += 2;
}
echo "تم فحص " . count($invoices) . " فاتورة\n";

echo "\n=== التحقق من المشتريات ===\n";
$purchases = DB::table('purchases')->get();
foreach ($purchases as $p) {
    if ($p->part_id !== null && DB::table('parts')->where('id', $p->part_id)->value('part_number') === 'OLD_DEBT') {
        continue;
    }
    $qty = $p->quantity - ($p->returned_quantity ?? 0);
    $expectedTotal = r($qty * $p->unit_price);
    if (r($p->total) !== $expectedTotal) {
        $issues[] = "شراء #{$p->id}: المجموع {$p->total} لا يساوي المتوقع {$expectedTotal}";
    }
    $expectedRemaining = r(max(0, $p->total - $p->paid - ($p->credit_used ?? 0)));
    if (r($p->remaining) !== $expectedRemaining) {
        $issues[] = "شراء #{$p->id}: المتبقي {$p->remaining} لا يساوي المتوقع {$expectedRemaining}";
    }
    $checks += 2;
}
echo "تم فحص " . count($purchases) . " عملية شراء\n";

echo "\n=== التحقق من العملاء ===\n";
$customers = DB::table('customers')->get();
foreach ($customers as $c) {
    // التحقق من أن العمود balance لا يتداخل مع balance_syp
    if (r($c->balance) !== 0.0 && r($c->balance_syp) !== 0.0) {
        $issues[] = "عميل #{$c->id} ({$c->name}): يوجد قيم في balance_syp ({$c->balance_syp}) و balance ({$c->balance}) معاً، مما يسبب تكراراً في عرض الرصيد";
    }
    $expected = ($c->balance_syp < 0 || $c->balance_usd < 0) ? 'مدين'
        : (($c->balance_syp > 0 || $c->balance_usd > 0) ? 'دائن' : 'متوان');
    if ($c->status !== $expected) {
        $issues[] = "عميل #{$c->id} ({$c->name}): الحالة '{$c->status}' لا تطابق الرصيد SYP={$c->balance_syp} USD={$c->balance_usd}";
    }
    $checks += 2;
}
echo "تم فحص " . count($customers) . " عميل\n";

echo "\n=== التحقق من الموردين ===\n";
$suppliers = DB::table('suppliers')->get();
foreach ($suppliers as $s) {
    if (r($s->balance) !== 0.0 && r($s->balance_syp) !== 0.0) {
        $issues[] = "مورد #{$s->id} ({$s->name}): يوجد قيم في balance_syp ({$s->balance_syp}) و balance ({$s->balance}) معاً، مما يسبب تكراراً في عرض الرصيد";
    }
    $expected = ($s->balance_syp > 0 || $s->balance_usd > 0) ? 'علينا'
        : (($s->balance_syp < 0 || $s->balance_usd < 0) ? 'لنا' : 'متوازن');
    if ($s->status !== $expected) {
        $issues[] = "مورد #{$s->id} ({$s->name}): الحالة '{$s->status}' لا تطابق الرصيد SYP={$s->balance_syp} USD={$s->balance_usd}";
    }
    $checks += 2;
}
echo "تم فحص " . count($suppliers) . " مورد\n";

echo "\n=== التحقق من الأرباح ===\n";
$profits = DB::table('profits')->get();
foreach ($profits as $p) {
    $q = $p->quantity - ($p->returned_quantity ?? 0);
    $expectedProfit = r($q * ($p->unit_price - $p->purchase_price));
    if (r($p->profit) !== $expectedProfit) {
        $issues[] = "ربح #{$p->id}: profit {$p->profit} لا يساوي المتوقع {$expectedProfit}";
    }
    $checks++;
}
echo "تم فحص " . count($profits) . " ربح\n";

echo "\n=== التحقق من المخزون ===\n";
$negativeStock = DB::table('parts')->where('quantity', '<', 0)->pluck('id');
if ($negativeStock->isNotEmpty()) {
    $issues[] = "قطع بكمية سالبة: " . $negativeStock->implode(', ');
} else {
    echo "لا توجد قطع بكمية سالبة\n";
}
$checks++;

echo "\n=== ملخص ===\n";
echo "عدد الاختبارات: {$checks}\n";
echo "عدد المشاكل: " . count($issues) . "\n";

if ($issues) {
    echo "\nالتفاصيل:\n";
    foreach ($issues as $issue) {
        echo "- {$issue}\n";
    }
} else {
    echo "\nلا توجد مشاكل، الأرقام متوافقة.\n";
}
