<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function randDate(int $daysBack = 60): string {
    return now()->subDays(rand(0, $daysBack))->toDateTimeString();
}

function randDateString(int $daysBack = 30): string {
    return now()->subDays(rand(0, $daysBack))->toDateString();
}

echo "=== بدء إضافة بيانات اختبارية ===\n\n";

// ─── 1. Categories ───
echo "📂 إضافة 25 فئة... ";
$categoryIds = [];
for ($i = 1; $i <= 25; $i++) {
    $categoryIds[] = DB::table('categories')->insertGetId([
        'name'        => 'فئة اختبار ' . $i,
        'created_at'  => randDate(),
        'updated_at'  => now(),
    ]);
}
echo "تم ✅\n";

// ─── 2. Customers ───
echo "👤 إضافة 25 عميل... ";
$customerIds = [];
for ($i = 1; $i <= 25; $i++) {
    $balSyp = rand(-500000, 500000);
    $customerIds[] = DB::table('customers')->insertGetId([
        'name'         => 'عميل اختبار ' . $i,
        'phone'        => '09' . rand(10000000, 99999999),
        'address'      => 'عنوان ' . $i,
        'balance'      => $balSyp,
        'balance_syp'  => $balSyp,
        'balance_usd'  => rand(-500, 500),
        'status'       => ['متوان', 'مدين', 'دائن'][rand(0, 2)],
        'created_at'   => randDate(),
        'updated_at'   => now(),
    ]);
}
echo "تم ✅\n";

// ─── 3. Suppliers ───
echo "🏭 إضافة 25 مورد... ";
$supplierIds = [];
for ($i = 1; $i <= 25; $i++) {
    $balSyp = rand(-500000, 500000);
    $supplierIds[] = DB::table('suppliers')->insertGetId([
        'name'         => 'مورد اختبار ' . $i,
        'phone'        => '09' . rand(10000000, 99999999),
        'address'      => 'عنوان ' . $i,
        'balance'      => $balSyp,
        'balance_syp'  => $balSyp,
        'balance_usd'  => rand(-500, 500),
        'status'       => ['علينا', 'متوازن', 'لنا'][rand(0, 2)],
        'created_at'   => randDate(),
        'updated_at'   => now(),
    ]);
}
echo "تم ✅\n";

// ─── 4. Parts ───
echo "🔧 إضافة 25 قطعة... ";
$partIds = [];
for ($i = 1; $i <= 25; $i++) {
    $qty = rand(1, 100);
    $pp  = rand(1000, 50000);
    $sp  = $pp + rand(1000, 10000);
    $partIds[] = DB::table('parts')->insertGetId([
        'name'              => 'قطعة اختبار ' . $i,
        'part_number'       => 'PN-' . strtoupper(Str::random(6)) . '-' . $i,
        'category_id'       => $categoryIds[array_rand($categoryIds)],
        'quantity'          => $qty,
        'purchase_price'    => $pp,
        'sale_price'        => $sp,
        'purchase_price_usd'=> rand(1, 50),
        'sale_price_usd'    => rand(2, 60),
        'notes'             => 'ملاحظات ' . $i,
        'alert_threshold'   => 5,
        'status'            => $qty <= 0 ? 'غير متوفر' : ($qty <= 5 ? 'منخفض' : 'متوفر'),
        'created_at'        => randDate(),
        'updated_at'        => now(),
    ]);
}
echo "تم ✅\n";

// ─── 5. Invoices + Invoice Items ───
echo "🧾 إضافة 25 فاتورة... ";
for ($i = 1; $i <= 25; $i++) {
    $total = rand(5000, 100000);
    $paid  = rand(0, $total);
    $rem   = $total - $paid;

    $invId = DB::table('invoices')->insertGetId([
        'customer_id'    => $customerIds[array_rand($customerIds)],
        'invoice_number' => 'INV-' . time() . '-' . $i . '-' . strtoupper(Str::random(4)),
        'total'          => $total,
        'paid'           => $paid,
        'credit_used'    => 0,
        'debt'           => 0,
        'remaining'      => $rem,
        'status'         => $rem > 0 ? 'عليه دين' : 'مسدد',
        'currency'       => ['SYP', 'USD'][rand(0, 1)],
        'exchange_rate'  => 1,
        'notes'          => 'ملاحظات فاتورة ' . $i,
        'sale_date'      => randDateString(),
        'created_at'     => randDate(30),
        'updated_at'     => now(),
    ]);

    $itemCount = rand(1, 3);
    for ($j = 0; $j < $itemCount; $j++) {
        $qty   = rand(1, 5);
        $price = rand(1000, 20000);
        DB::table('invoice_items')->insert([
            'invoice_id'       => $invId,
            'part_id'          => $partIds[array_rand($partIds)],
            'quantity'         => $qty,
            'returned_quantity'=> 0,
            'unit_price'       => $price,
            'total'            => $qty * $price,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }
}
echo "تم ✅\n";

// ─── 6. Purchases ───
echo "📥 إضافة 25 عملية شراء... ";
for ($i = 1; $i <= 25; $i++) {
    $qty   = rand(1, 50);
    $price = rand(1000, 20000);
    $total = $qty * $price;
    $paid  = rand(0, $total);

    DB::table('purchases')->insert([
        'supplier_id'      => $supplierIds[array_rand($supplierIds)],
        'part_id'          => $partIds[array_rand($partIds)],
        'quantity'         => $qty,
        'returned_quantity'=> 0,
        'unit_price'       => $price,
        'total'            => $total,
        'paid'             => $paid,
        'credit_used'      => 0,
        'remaining'        => $total - $paid,
        'status'           => ($total - $paid) > 0 ? 'علينا دين' : 'مسدد',
        'currency'         => ['SYP', 'USD'][rand(0, 1)],
        'purchase_date'    => randDateString(),
        'notes'            => 'ملاحظات شراء ' . $i,
        'created_at'       => randDate(30),
        'updated_at'       => now(),
    ]);
}
echo "تم ✅\n";

// ─── 7. Payments ───
echo "💳 إضافة 25 دفعة عميل... ";
for ($i = 1; $i <= 25; $i++) {
    DB::table('payments')->insert([
        'customer_id'   => $customerIds[array_rand($customerIds)],
        'amount'        => rand(1000, 50000),
        'currency'      => ['SYP', 'USD'][rand(0, 1)],
        'notes'         => 'دفعة ' . $i,
        'payment_date'  => randDateString(),
        'created_at'    => randDate(30),
        'updated_at'    => now(),
    ]);
}
echo "تم ✅\n";

// ─── 8. Supplier Payments ───
echo "💵 إضافة 25 دفعة مورد... ";
for ($i = 1; $i <= 25; $i++) {
    DB::table('supplier_payments')->insert([
        'supplier_id'   => $supplierIds[array_rand($supplierIds)],
        'amount'        => rand(1000, 50000),
        'currency'      => ['SYP', 'USD'][rand(0, 1)],
        'notes'         => 'دفعة مورد ' . $i,
        'payment_date'  => randDateString(),
        'created_at'    => randDate(30),
        'updated_at'    => now(),
    ]);
}
echo "تم ✅\n";

// ─── 9. Withdrawals ───
echo "💸 إضافة 25 سحب... ";
for ($i = 1; $i <= 25; $i++) {
    DB::table('withdrawals')->insert([
        'person_name'      => 'شخص ' . $i,
        'amount'           => rand(1000, 50000),
        'currency'         => ['SYP', 'USD'][rand(0, 1)],
        'reason'           => 'سبب السحب ' . $i,
        'withdrawal_date'  => randDateString(),
        'notes'            => 'ملاحظات ' . $i,
        'created_at'       => randDate(30),
        'updated_at'       => now(),
    ]);
}
echo "تم ✅\n";

// ─── 10. Backups ───
echo "💾 إضافة 25 نسخة احتياطية... ";
for ($i = 1; $i <= 25; $i++) {
    $fmt = ['excel', 'sql', 'sqlite'][rand(0, 2)];
    $ext = ['xlsx', 'sql', 'sqlite'][rand(0, 2)];
    DB::table('backups')->insert([
        'filename'    => 'backup_' . time() . '_' . $i . '.' . $ext,
        'path'        => '/backups/backup_' . $i,
        'format'      => $fmt,
        'size'        => rand(1000, 10000000),
        'created_at'  => randDate(),
        'updated_at'  => now(),
    ]);
}
echo "تم ✅\n";

echo "\n=== اكتملت إضافة البيانات الاختبارية بنجاح 🎉 ===\n";
echo "أعد تحميل الصفحة (F5) لرؤية البيانات في الجداول.\n";
