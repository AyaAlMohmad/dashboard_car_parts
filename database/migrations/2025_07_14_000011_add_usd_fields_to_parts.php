<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parts', function (Blueprint $table) {
            $table->decimal('sale_price_usd', 12, 2)->default(0)->after('sale_price');
            $table->decimal('purchase_price_usd', 12, 2)->default(0)->after('purchase_price');
        });
    }

    public function down(): void
    {
        Schema::table('parts', function (Blueprint $table) {
            $table->dropColumn('sale_price_usd');
            $table->dropColumn('purchase_price_usd');
        });
    }
};
