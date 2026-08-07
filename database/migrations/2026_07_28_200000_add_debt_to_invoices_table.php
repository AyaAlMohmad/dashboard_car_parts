<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('debt', 12, 2)->default(0)->after('credit_used');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('invoices', 'debt')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('debt');
            });
        }
    }
};
