<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('profits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('invoice_item_id')->nullable()->constrained('invoice_items')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('part_id')->nullable()->constrained('parts')->nullOnDelete();
            $table->integer('quantity')->default(0);
            $table->integer('returned_quantity')->default(0);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('purchase_price', 12, 2)->default(0);
            $table->string('currency', 3)->default('SYP');
            $table->date('sale_date')->nullable();
            $table->decimal('profit', 12, 2)->default(0);
            $table->decimal('returned_profit', 12, 2)->default(0);
            $table->timestamps();
        });

        DB::table('invoice_items as ii')
            ->join('invoices as i', 'ii.invoice_id', '=', 'i.id')
            ->join('parts as p', 'ii.part_id', '=', 'p.id')
            ->select(
                'i.id as invoice_id',
                'ii.id as invoice_item_id',
                'i.customer_id',
                'ii.part_id',
                'ii.quantity',
                DB::raw('COALESCE(ii.returned_quantity, 0) as returned_quantity'),
                'ii.unit_price',
                DB::raw('CASE WHEN i.currency = "USD" THEN COALESCE(p.purchase_price_usd, 0) ELSE COALESCE(p.purchase_price, 0) END as purchase_price'),
                'i.currency',
                'i.sale_date',
                DB::raw('ii.quantity * (ii.unit_price - (CASE WHEN i.currency = "USD" THEN COALESCE(p.purchase_price_usd, 0) ELSE COALESCE(p.purchase_price, 0) END)) as profit'),
                DB::raw('COALESCE(ii.returned_quantity, 0) * (ii.unit_price - (CASE WHEN i.currency = "USD" THEN COALESCE(p.purchase_price_usd, 0) ELSE COALESCE(p.purchase_price, 0) END)) as returned_profit')
            )
            ->orderBy('ii.id')
            ->chunk(500, function ($rows) {
                $now = now();
                foreach ($rows as $row) {
                    DB::table('profits')->insert([
                        'invoice_id' => $row->invoice_id,
                        'invoice_item_id' => $row->invoice_item_id,
                        'customer_id' => $row->customer_id,
                        'part_id' => $row->part_id,
                        'quantity' => $row->quantity,
                        'returned_quantity' => $row->returned_quantity,
                        'unit_price' => $row->unit_price,
                        'purchase_price' => $row->purchase_price,
                        'currency' => $row->currency,
                        'sale_date' => $row->sale_date,
                        'profit' => $row->profit,
                        'returned_profit' => $row->returned_profit,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profits');
    }
};
