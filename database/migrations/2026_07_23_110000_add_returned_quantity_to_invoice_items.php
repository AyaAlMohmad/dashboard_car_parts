<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->integer('returned_quantity')->default(0)->after('quantity');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('invoice_items', 'returned_quantity')) {
            Schema::table('invoice_items', function (Blueprint $table) {
                $table->dropColumn('returned_quantity');
            });
        }
    }
};
