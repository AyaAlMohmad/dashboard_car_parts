<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Part;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

// الحصول على أو إنشاء فئة تجريبية
$cat = Category::first();
if (!$cat) {
    $cat = Category::create(['name' => 'فئة تجريبية']);
}

echo "=== إضافة 50 عنصر للمستودع ===\n";
$created = 0;
for ($i = 1; $i <= 50; $i++) {
    $unique = 'TEST-' . time() . '-' . $i;
    Part::create([
        'name' => 'قطعة اختبار ' . $i,
        'part_number' => $unique,
        'category_id' => $cat->id,
        'quantity' => rand(1, 100),
        'purchase_price' => rand(1000, 50000),
        'purchase_price_usd' => rand(10, 500),
        'sale_price' => rand(1000, 50000),
        'sale_price_usd' => rand(10, 500),
        'alert_threshold' => 5,
        'notes' => 'عنصر اختبار تلقائي',
        'status' => 'نشط',
    ]);
    $created++;
    echo "  أنشئ: {$unique}\n";
}

echo "\n=== التحقق من العدد ===\n";
$total = Part::where('part_number', 'like', 'TEST-%')->count();
echo "عدد العناصر التجريبية في قاعدة البيانات: {$total}\n";

// محاكاة ما يفعله loadParts في JS
$page = 1;
$allParts = [];
while (true) {
    $res = DB::table('parts')
        ->where('part_number', '!=', 'OLD_DEBT')
        ->orderBy('created_at', 'desc')
        ->paginate(20, ['*'], 'page', $page);
    $allParts = array_merge($allParts, $res->items());
    if ($page >= $res->lastPage()) break;
    $page++;
}

echo "عدد الأصناف المحاكاة (جميع الصفحات): " . count($allParts) . "\n";
$testCount = collect($allParts)->where('part_number', 'like', 'TEST-%')->count();
echo "منها عناصر اختبار: {$testCount}\n";

if ($testCount === 50) {
    echo "\n✅ النجاح: جميع الـ 50 عنصر ظاهرين!\n";
} else {
    echo "\n❌ فشل: ظهر فقط {$testCount} من 50 عنصر.\n";
}
