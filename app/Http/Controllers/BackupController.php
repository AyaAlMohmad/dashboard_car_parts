<?php

namespace App\Http\Controllers;

use App\Exports\DatabaseBackupExport;
use App\Exports\TableExport;
use App\Models\Backup;
use App\Models\BackupSchedule;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Invoice;
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
use App\Models\Setting;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\BackupMail;

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

        $backup = Backup::latest()->first();
        $emailStatus = null;
        if ($backup && Setting::get('email_enabled') === '1') {
            try {
                $result = $this->sendBackupByEmail($backup);
                $emailStatus = 'sent';
            } catch (\Exception $e) {
                Log::warning('Auto-send email failed: ' . $e->getMessage());
                $emailStatus = 'failed';
            }
        }

        $message = 'تم إنشاء النسخة الاحتياطية';
        if ($emailStatus === 'sent') {
            $message .= ' وتم إرسالها بالبريد ✅';
        } elseif ($emailStatus === 'failed') {
            $message .= ' (فشل الإرسال بالبريد)';
        }

        return response()->json(['message' => $message], 201);
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
            'invoices'     => [Invoice::class, 'invoices'],
            'sales'        => [Sale::class, 'sales'],
            'payments'     => [Payment::class, 'payments'],
            'suppliers'    => [Supplier::class, 'suppliers'],
            'purchases'    => [Purchase::class, 'purchases'],
            'supplier_payments' => [SupplierPayment::class, 'supplier_payments'],
            'categories'   => [Category::class, 'categories'],
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

    private function convertInterval(string $interval): array
    {
        return match ($interval) {
            'hourly'  => ['interval_days' => 0, 'interval_hours' => 1, 'interval_minutes' => 0],
            'daily'   => ['interval_days' => 1, 'interval_hours' => 0, 'interval_minutes' => 0],
            'weekly'  => ['interval_days' => 7, 'interval_hours' => 0, 'interval_minutes' => 0],
            'monthly' => ['interval_days' => 30, 'interval_hours' => 0, 'interval_minutes' => 0],
            default   => ['interval_days' => 1, 'interval_hours' => 0, 'interval_minutes' => 0],
        };
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

        $intervalData = $this->convertInterval($validated['interval']);
        $schedule = BackupSchedule::create(array_merge($validated, $intervalData));
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

        if (isset($validated['interval'])) {
            $validated = array_merge($validated, $this->convertInterval($validated['interval']));
            unset($validated['interval']);
        }

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
            'interval_label' => $schedule->intervalLabel(),
            'next_run_at' => $schedule->nextRunAt()->toDateTimeString(),
            'seconds_remaining' => max(0, now()->diffInSeconds($schedule->nextRunAt(), false)),
        ]);
    }

    public function runSchedule(BackupSchedule $schedule): JsonResponse
    {
        Artisan::call('backup:database', [
            '--format' => $schedule->format,
            '--type' => $schedule->type,
        ]);
        $schedule->update(['last_run_at' => now()]);
        return response()->json(['message' => 'تم تشغيل الجدولة', 'last_run_at' => now()->toDateTimeString()]);
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

    // --- Email Settings ---
    public function emailSettings(): JsonResponse
    {
        return response()->json([
            'enabled' => (bool) Setting::get('email_enabled', false),
            'to' => Setting::get('email_to', ''),
            'smtp_host' => Setting::get('email_smtp_host', ''),
            'smtp_port' => Setting::get('email_smtp_port', '587'),
            'smtp_user' => Setting::get('email_smtp_user', ''),
            'smtp_pass' => Setting::get('email_smtp_pass', '') ? '********' : '',
            'smtp_encryption' => Setting::get('email_smtp_encryption', 'tls'),
        ]);
    }

    public function saveEmailSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => 'boolean',
            'to' => 'required_if:enabled,true|email',
            'smtp_host' => 'nullable|string',
            'smtp_port' => 'nullable|string',
            'smtp_user' => 'nullable|string',
            'smtp_pass' => 'nullable|string',
            'smtp_encryption' => 'nullable|string',
        ]);

        Setting::set('email_enabled', $validated['enabled'] ? '1' : '0');
        Setting::set('email_to', $validated['to'] ?? '');
        Setting::set('email_smtp_host', $validated['smtp_host'] ?? '');
        Setting::set('email_smtp_port', $validated['smtp_port'] ?? '587');
        Setting::set('email_smtp_user', $validated['smtp_user'] ?? '');
        Setting::set('email_smtp_encryption', $validated['smtp_encryption'] ?? 'tls');
        if (!empty($validated['smtp_pass']) && $validated['smtp_pass'] !== '********') {
            Setting::set('email_smtp_pass', $validated['smtp_pass']);
        }

        return response()->json(['message' => 'تم حفظ إعدادات البريد']);
    }

    private function sendBackupByEmail(Backup $backup): JsonResponse
    {
        $to = Setting::get('email_to');
        if (!$to) {
            Log::warning('Email not sent: no recipient email configured');
            return response()->json(['message' => 'عنوان البريد غير مُحدد'], 400);
        }

        $path = Storage::disk('local')->path($backup->path);
        if (!file_exists($path)) {
            $path = storage_path('app/' . $backup->path);
        }
        if (!file_exists($path)) {
            Log::warning('Email not sent: backup file not found at ' . $path);
            return response()->json(['message' => 'ملف النسخة غير موجود'], 404);
        }

        Log::info('Attempting to send backup email to: ' . $to);
        Log::info('Backup file path: ' . $path);

        // Apply custom SMTP settings if provided
        $smtpHost = Setting::get('email_smtp_host');
        if ($smtpHost) {
            Log::info('Using custom SMTP: ' . $smtpHost);
            config([
                'mail.mailers.smtp.host' => $smtpHost,
                'mail.mailers.smtp.port' => (int) Setting::get('email_smtp_port', '587'),
                'mail.mailers.smtp.username' => Setting::get('email_smtp_user', ''),
                'mail.mailers.smtp.password' => Setting::get('email_smtp_pass', ''),
                'mail.mailers.smtp.encryption' => Setting::get('email_smtp_encryption', 'tls'),
                'mail.from.address' => Setting::get('email_smtp_user', $to),
                'mail.from.name' => 'نظام إدارة متجر قطع السيارات',
            ]);
        } else {
            Log::info('Using default Laravel mail configuration');
        }

        try {
            Mail::to($to)->send(new BackupMail($backup, $path));
            Log::info('Backup email sent successfully to: ' . $to);
            return response()->json(['message' => '✅ تم إرسال النسخة إلى البريد']);
        } catch (\Exception $e) {
            Log::error('Email sending failed: ' . $e->getMessage());
            Log::error('Email error trace: ' . $e->getTraceAsString());
            return response()->json(['message' => 'خطأ في الإرسال: ' . $e->getMessage()], 500);
        }
    }

    public function sendBackup(Backup $backup): JsonResponse
    {
        return $this->sendBackupByEmail($backup);
    }
}
