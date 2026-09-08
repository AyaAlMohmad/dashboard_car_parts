<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Purchase;

$p = Purchase::with('part')->find(6);
if ($p) {
    echo "Eloquent #6: total={$p->total} paid={$p->paid} credit_used={$p->credit_used} remaining={$p->remaining}\n";
    echo "attributes: " . json_encode($p->toArray(), JSON_UNESCAPED_UNICODE) . "\n";
}
