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
        if (!Schema::hasColumn('purchases', 'returned_quantity')) {
            Schema::table('purchases', function (Blueprint $table) {
                $table->integer('returned_quantity')->default(0)->after('quantity');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('purchases', 'returned_quantity')) {
            Schema::table('purchases', function (Blueprint $table) {
                $table->dropColumn('returned_quantity');
            });
        }
    }
};
