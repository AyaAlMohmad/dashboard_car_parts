<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Part;

// محاكاة ما يفعله apiFetch في JS
$page = 1;
$allPartsData = [];
while (true) {
    $res = Part::with('category')->where('part_number', '!=', 'OLD_DEBT')->latest()->paginate(20, ['*'], 'page', $page);
    $allPartsData = array_merge($allPartsData, $res->items());
    if ($page >= $res->lastPage()) break;
    $page++;
    if ($page > 100) break;
}

echo "=== محاكاة apiFetch('/parts?page=...') ===\n";
echo "جمعت " . count($allPartsData) . " عنصر من {$res->lastPage()} صفحات\n";

$testCount = collect($allPartsData)->filter(fn($p) => str_starts_with($p->part_number, 'TEST-'))->count();
echo "عناصر TEST ضمن الجمع: {$testCount}\n";

echo "\n=== عينة من العناصر (أول 5 + آخر 5) ===\n";
foreach (array_slice($allPartsData, 0, 5) as $p) {
    echo "  #{$p->id} {$p->name} ({$p->part_number})\n";
}
echo "  ...\n";
foreach (array_slice($allPartsData, -5) as $p) {
    echo "  #{$p->id} {$p->name} ({$p->part_number})\n";
}

if (count($allPartsData) >= 50) {
    echo "\n✅ النجاح: جميع الأصناف تُجمع بشكل صحيح عبر الصفحات!\n";
} else {
    echo "\n❌ فشل.\n";
}
