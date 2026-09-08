<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Part;

$testParts = Part::where('part_number', 'like', 'TEST-%')->get();
echo "=== عناصر الاختبار في قاعدة البيانات ===\n";
echo "العدد: " . $testParts->count() . "\n";
foreach ($testParts->take(5) as $p) {
    echo "  #{$p->id} {$p->name} ({$p->part_number}) qty={$p->quantity}\n";
}
if ($testParts->count() > 5) echo "  ... و " . ($testParts->count() - 5) . " أخرى\n";

echo "\n=== العدد الكلي للأصناف ===\n";
echo "الكل: " . Part::count() . "\n";

// محاكاة استجابة API كما تراها JavaScript
$page1 = Part::with('category')->where('part_number', '!=', 'OLD_DEBT')->latest()->paginate(20);
echo "\nصفحة 1: " . count($page1->items()) . " عنصر\n";
echo "آخر صفحة: {$page1->lastPage()}\n";

$allApiParts = [];
for ($pg = 1; $pg <= $page1->lastPage(); $pg++) {
    $page = Part::with('category')->where('part_number', '!=', 'OLD_DEBT')->latest()->paginate(20, ['*'], 'page', $pg);
    $allApiParts = array_merge($allApiParts, $page->items());
}
echo "مجموع كل الصفحات: " . count($allApiParts) . "\n";

$testInAll = collect($allApiParts)->filter(fn($p) => str_starts_with($p->part_number, 'TEST-'))->count();
echo "عناصر اختبار ضمن الكل: {$testInAll}\n";
