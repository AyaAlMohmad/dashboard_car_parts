<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('backup_schedules', function (Blueprint $table) {
            $table->dropColumn('interval');
            $table->unsignedInteger('interval_days')->default(0)->after('format');
            $table->unsignedInteger('interval_hours')->default(0)->after('interval_days');
            $table->unsignedInteger('interval_minutes')->default(0)->after('interval_hours');
        });
    }

    public function down(): void
    {
        Schema::table('backup_schedules', function (Blueprint $table) {
            $table->dropColumn(['interval_days', 'interval_hours', 'interval_minutes']);
            $table->enum('interval', ['hourly', 'daily', 'weekly', 'monthly'])->default('daily')->after('format');
        });
    }
};
