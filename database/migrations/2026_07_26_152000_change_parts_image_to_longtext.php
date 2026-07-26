<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE parts MODIFY image LONGTEXT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE parts MODIFY image VARCHAR(255) NULL');
    }
};
