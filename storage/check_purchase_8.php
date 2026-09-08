<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$purchases = DB::table('purchases')
    ->leftJoin('parts', 'purchases.part_id', '=', 'parts.id')
    ->leftJoin('suppliers', 'purchases.supplier_id', '=', 'suppliers.id')
    ->where('purchases.id', 8)
    ->orWhere('purchases.id', 9)
    ->select('purchases.*', 'parts.name as part_name', 'parts.part_number', 'suppliers.name as supplier_name', 'suppliers.balance_usd', 'suppliers.balance_syp')
    ->get();

foreach ($purchases as $p) {
    echo "#{$p->id} supplier={$p->supplier_name}({$p->supplier_id}) part={$p->part_name}({$p->part_number}) total={$p->total} paid={$p->paid} credit_used={$p->credit_used} remaining={$p->remaining} currency={$p->currency} balance_usd={$p->balance_usd} balance_syp={$p->balance_syp}\n";
}
