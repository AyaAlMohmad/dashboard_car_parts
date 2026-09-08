<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$customer = DB::table('customers')->where('phone', '1234567')->first();
if (!$customer) {
    echo "Customer not found\n";
    exit;
}

echo "Customer #{$customer->id} {$customer->name}\n";
echo "balance_syp={$customer->balance_syp}, balance_usd={$customer->balance_usd}, status={$customer->status}\n\n";

$payments = DB::table('payments')->where('customer_id', $customer->id)->orderBy('id', 'desc')->get();
foreach ($payments as $p) {
    echo "Payment #{$p->id}: amount={$p->amount} currency={$p->currency} notes='{$p->notes}' created_at={$p->created_at}\n";
}
