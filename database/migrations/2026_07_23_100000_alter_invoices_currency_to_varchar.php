<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE invoices MODIFY COLUMN currency VARCHAR(10) NOT NULL DEFAULT 'SYP'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE invoices MODIFY COLUMN currency ENUM('SYP','USD') NOT NULL DEFAULT 'SYP'");
    }
};
