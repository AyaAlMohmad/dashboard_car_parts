<?php

namespace App\Http\Controllers;

use App\Exports\DatabaseBackupExport;
use App\Exports\TableExport;
use App\Models\Backup;
use App\Models\BackupSchedule;
use App\Models\Customer;
use App\Models\Part;
use App\Models\Sale;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\Purchase;
use App\Models\SupplierPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function index(): JsonResponse
    {
        $backups = Backup::latest()->paginate(20);
        return response()->json($backups);
    }

    public function store(Request $request): JsonResponse
    {
        $format = $request->input('format', 'excel');
        Artisan::call('backup:database', ['--format' => $format]);
        return response()->json(['message' => 'Backup created'], 201);
    }

    public function download(Backup $backup): BinaryFileResponse
    {
        $path = Storage::disk('local')->path($backup->path);
        if (!file_exists($path)) {
            // Fallback for old backups stored outside private dir
            $legacyPath = storage_path('app/' . $backup->path);
            if (file_exists($legacyPath)) {
                $path = $legacyPath;
            } else {
                abort(404, 'Backup file not found');
            }
        }
        return response()->download($path, $backup->filename);
    }

    public function downloadExcel(): BinaryFileResponse
    {
        $filename = 'backup_' . now()->format('Y-m-d_His') . '.xlsx';
        (new DatabaseBackupExport)->store($filename, 'local');
        $path = Storage::disk('local')->path($filename);
        return response()->download($path, $filename)->deleteFileAfterSend();
    }

    // --- Table export ---
    public function exportTable(Request $request): BinaryFileResponse
    {
        $table = $request->input('table', 'full');
        $format = $request->input('format', 'excel');

        $map = [
            'customers'    => [Customer::class, 'customers'],
            'parts'        => [Part::class, 'parts'],
            'sales'        => [Sale::class, 'sales'],
            'payments'     => [Payment::class, 'payments'],
            'suppliers'    => [Supplier::class, 'suppliers'],
            'purchases'    => [Purchase::class, 'purchases'],
            'supplier_payments' => [SupplierPayment::class, 'supplier_payments'],
        ];

        if ($table === 'full') {
            $filename = 'full_backup_' . now()->format('Y-m-d_His') . '.xlsx';
            (new DatabaseBackupExport)->store($filename, 'local');
            return response()->download(Storage::disk('local')->path($filename), $filename)->deleteFileAfterSend();
        }

        if (!isset($map[$table])) {
            abort(404, 'Table not found');
        }

        [$modelClass, $title] = $map[$table];

        if ($format === 'excel') {
            $filename = $table . '_' . now()->format('Y-m-d_His') . '.xlsx';
            (new TableExport($modelClass, $title))->store($filename, 'local');
            return response()->download(Storage::disk('local')->path($filename), $filename)->deleteFileAfterSend();
        }

        // SQL format
        $filename = $table . '_' . now()->format('Y-m-d_His') . '.sql';
        $tempPath = 'temp/' . $filename;
        Storage::disk('local')->put($tempPath, $this->dumpTableSql($table, $modelClass));
        return response()->download(Storage::disk('local')->path($tempPath), $filename)->deleteFileAfterSend();
    }

    private function dumpTableSql(string $table, string $modelClass): string
    {
        $model = new $modelClass;
        $tableName = $model->getTable();
        $sql = "-- Export of {$tableName} generated " . now() . "\n\n";

        $create = \DB::select("SHOW CREATE TABLE `{$tableName}`")[0];
        $key = 'Create Table';
        $sql .= $create->{$key} . ";\n\n";

        $rows = $model->newQuery()->get();
        foreach ($rows as $row) {
            $vals = collect($row->toArray())->map(function ($v) {
                return is_null($v) ? 'NULL' : "'" . addslashes($v) . "'";
            })->implode(', ');
            $sql .= "INSERT INTO `{$tableName}` VALUES ({$vals});\n";
        }
        return $sql;
    }

    // --- Schedules ---
    public function schedules(): JsonResponse
    {
        return response()->json(BackupSchedule::orderBy('id', 'desc')->get());
    }

    public function storeSchedule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => 'nullable|string|max:255',
            'type'     => 'required|in:full,customers,parts,sales,payments,suppliers,purchases,supplier_payments',
            'format'   => 'required|in:excel,sql',
            'interval' => 'required|in:hourly,daily,weekly,monthly',
            'enabled'  => 'boolean',
        ]);

        $schedule = BackupSchedule::create($validated);
        return response()->json($schedule, 201);
    }

    public function updateSchedule(Request $request, BackupSchedule $schedule): JsonResponse
    {
        $validated = $request->validate([
            'name'     => 'nullable|string|max:255',
            'type'     => 'in:full,customers,parts,sales,payments,suppliers,purchases,supplier_payments',
            'format'   => 'in:excel,sql',
            'interval' => 'in:hourly,daily,weekly,monthly',
            'enabled'  => 'boolean',
        ]);

        $schedule->update($validated);
        return response()->json($schedule);
    }

    public function destroySchedule(BackupSchedule $schedule): JsonResponse
    {
        $schedule->delete();
        return response()->json(['message' => 'Deleted']);
    }

    public function nextRun(): JsonResponse
    {
        $schedule = BackupSchedule::where('enabled', true)
            ->orderBy('last_run_at', 'asc')
            ->first();

        if (!$schedule) {
            return response()->json(['message' => 'No active schedules']);
        }

        return response()->json([
            'schedule_id' => $schedule->id,
            'name' => $schedule->name,
            'type' => $schedule->type,
            'format' => $schedule->format,
            'interval' => $schedule->interval,
            'next_run_at' => $schedule->nextRunAt()->toDateTimeString(),
            'seconds_remaining' => max(0, now()->diffInSeconds($schedule->nextRunAt(), false)),
        ]);
    }

    public function destroy(Backup $backup): JsonResponse
    {
        $path = Storage::disk('local')->path($backup->path);
        if (file_exists($path)) {
            unlink($path);
        } else {
            $legacyPath = storage_path('app/' . $backup->path);
            if (file_exists($legacyPath)) {
                unlink($legacyPath);
            }
        }
        $backup->delete();
        return response()->json(['message' => 'Backup deleted']);
    }
}
