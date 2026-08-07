<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE customers MODIFY COLUMN status ENUM('متوان', 'مدين', 'دائن') NOT NULL DEFAULT 'متوان'");
    }

    public function down(): void
    {
        DB::statement("UPDATE customers SET status = 'متوان' WHERE status = 'دائن'");
        DB::statement("ALTER TABLE customers MODIFY COLUMN status ENUM('متوان', 'مدين') NOT NULL DEFAULT 'متوان'");
    }
};
