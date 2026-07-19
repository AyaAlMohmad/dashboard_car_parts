<?php

namespace App\Console\Commands;

use App\Exports\DatabaseBackupExport;
use App\Exports\TableExport;
use App\Models\Backup;
use App\Models\Customer;
use App\Models\Part;
use App\Models\Sale;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\Purchase;
use App\Models\SupplierPayment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database {--format=excel : excel or sqlite} {--type=full : full, customers, parts, sales, payments, suppliers, purchases, supplier_payments}';
    protected $description = 'Export database to backup file';

    public function handle(): int
    {
        $format = $this->option('format');
        $type = $this->option('type');
        Storage::disk('local')->makeDirectory('backups');

        $map = [
            'customers' => [Customer::class, 'customers'],
            'parts' => [Part::class, 'parts'],
            'sales' => [Sale::class, 'sales'],
            'payments' => [Payment::class, 'payments'],
            'suppliers' => [Supplier::class, 'suppliers'],
            'purchases' => [Purchase::class, 'purchases'],
            'supplier_payments' => [SupplierPayment::class, 'supplier_payments'],
        ];

        if ($format === 'excel') {
            if ($type === 'full' || !isset($map[$type])) {
                $filename = 'backup_' . now()->format('Y-m-d_His') . '.xlsx';
                $path = 'backups/' . $filename;
                (new DatabaseBackupExport)->store($path, 'local');
            } else {
                [$modelClass, $title] = $map[$type];
                $filename = $type . '_' . now()->format('Y-m-d_His') . '.xlsx';
                $path = 'backups/' . $filename;
                (new TableExport($modelClass, $title))->store($path, 'local');
            }
            $fullPath = Storage::disk('local')->path($path);
            $size = file_exists($fullPath) ? filesize($fullPath) : 0;
        } else {
            $filename = 'backup_' . now()->format('Y-m-d_His') . '.sqlite';
            $path = 'backups/' . $filename;
            $this->dumpSqlite($filename);
            $fullPath = Storage::disk('local')->path($path);
            $size = file_exists($fullPath) ? filesize($fullPath) : 0;
        }

        Backup::create([
            'filename' => $filename,
            'path' => $path,
            'format' => $format,
            'size' => $size,
        ]);

        $this->info('Backup saved to: ' . $fullPath);
        return 0;
    }

    private function ensureDir(string $path): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    private function dumpSqlite(string $filename): void
    {
        $dest = Storage::disk('local')->path('backups/' . $filename);
        $this->ensureDir($dest);

        // Only copy SQLite file if we are actually using SQLite
        if (config('database.default') === 'sqlite') {
            $source = database_path('database.sqlite');
            if (file_exists($source)) {
                copy($source, $dest);
                return;
            }
        }

        // MySQL dump via mysqldump if available, else SQL dump
        $db = env('DB_DATABASE');
        $user = env('DB_USERNAME');
        $pass = env('DB_PASSWORD');
        $host = env('DB_HOST');
        $port = env('DB_PORT', 3306);
        $passArg = $pass ? "-p{$pass}" : '';
        $cmd = "mysqldump -h {$host} -P {$port} -u {$user} {$passArg} {$db}";
        $output = shell_exec($cmd);
        if ($output && strlen($output) > 50) {
            file_put_contents($dest, $output);
        } else {
            // Fallback: dump tables structure + data manually
            file_put_contents($dest, $this->manualSqlDump());
        }
    }

    private function manualSqlDump(): string
    {
        $sql = "-- SQL dump generated " . now() . "\n";
        $tables = DB::select('SHOW TABLES');
        $key = 'Tables_in_' . env('DB_DATABASE');
        foreach ($tables as $table) {
            $t = $table->{$key};
            $sql .= "\n-- Table: {$t}\n";
            $create = DB::select("SHOW CREATE TABLE `{$t}`")[0];
            $createKey = 'Create Table';
            $sql .= $create->{$createKey} . ";\n\n";
            $rows = DB::table($t)->get();
            foreach ($rows as $row) {
                $vals = collect((array)$row)->map(function ($v) {
                    return is_null($v) ? 'NULL' : "'" . addslashes($v) . "'";
                })->implode(', ');
                $sql .= "INSERT INTO `{$t}` VALUES ({$vals});\n";
            }
        }
        return $sql;
    }
}
