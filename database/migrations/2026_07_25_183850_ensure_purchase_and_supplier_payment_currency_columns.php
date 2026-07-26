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
        Schema::table('purchases', function (Blueprint $table) {
            if (!Schema::hasColumn('purchases', 'currency')) {
                $table->enum('currency', ['SYP', 'USD'])->default('SYP')->after('total');
            }
        });

        Schema::table('supplier_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('supplier_payments', 'currency')) {
                $table->enum('currency', ['SYP', 'USD'])->default('SYP')->after('amount');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            if (Schema::hasColumn('purchases', 'currency')) {
                $table->dropColumn('currency');
            }
        });

        Schema::table('supplier_payments', function (Blueprint $table) {
            if (Schema::hasColumn('supplier_payments', 'currency')) {
                $table->dropColumn('currency');
            }
        });
    }
};
