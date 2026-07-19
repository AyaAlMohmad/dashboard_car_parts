<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->string('invoice_number')->unique();
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('paid', 12, 2)->default(0);
            $table->decimal('remaining', 12, 2)->default(0);
            $table->enum('status', ['مسدد', 'عليه دين'])->default('عليه دين');
            $table->enum('currency', ['SYP', 'USD'])->default('SYP');
            $table->decimal('exchange_rate', 12, 2)->default(1);
            $table->text('notes')->nullable();
            $table->date('sale_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
