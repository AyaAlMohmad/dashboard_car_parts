<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::table('purchases')->where('id', '<=', 10)->orderBy('id')->get();
foreach ($rows as $p) {
    $part = DB::table('parts')->find($p->part_id);
    $pn = $part ? $part->part_number : '?';
    echo "#{$p->id} supplier={$p->supplier_id} part={$p->part_id}({$pn}) total={$p->total} paid={$p->paid} credit_used={$p->credit_used} remaining={$p->remaining} currency={$p->currency} date={$p->purchase_date}\n";
}
