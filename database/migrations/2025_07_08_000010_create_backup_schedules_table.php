<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable(); // optional label
            $table->enum('type', ['full', 'customers', 'parts', 'sales', 'payments', 'suppliers', 'purchases', 'supplier_payments'])->default('full');
            $table->enum('format', ['excel', 'sql'])->default('excel');
            $table->enum('interval', ['hourly', 'daily', 'weekly', 'monthly'])->default('daily');
            $table->timestamp('last_run_at')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_schedules');
    }
};
