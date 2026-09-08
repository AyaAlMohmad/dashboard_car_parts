<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$protected = ['settings', 'migrations', 'users', 'password_resets', 'personal_access_tokens', 'failed_jobs', 'jobs', 'cache', 'cache_locks', 'sessions'];
$rows = DB::select('SHOW TABLES');
$tables = collect($rows)
    ->map(fn($r) => (array) $r)
    ->map(fn($r) => array_values($r)[0])
    ->sort()
    ->values()
    ->all();

if (!$tables) {
    echo "لم يتم العثور على جداول.\n";
    exit(0);
}

echo "الجداول المحمية: " . implode(', ', array_intersect($protected, $tables)) . "\n";
echo "سيتم تفريغ: " . implode(', ', array_diff($tables, $protected)) . "\n\n";

try {
    DB::statement('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($tables as $table) {
        if (!$table || in_array($table, $protected, true)) {
            continue;
        }
        DB::statement('TRUNCATE TABLE `' . $table . '`');
        echo "تم تفريغ: {$table}\n";
    }
} finally {
    DB::statement('SET FOREIGN_KEY_CHECKS = 1');
}

echo "\nتم الانتهاء.\n";
