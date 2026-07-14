<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('part_number')->unique();
            $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
            $table->integer('quantity')->default(0);
            $table->decimal('purchase_price', 12, 2)->default(0);
            $table->decimal('sale_price', 12, 2)->default(0);
            $table->string('supplier')->nullable();
            $table->integer('alert_threshold')->default(5);
            $table->enum('status', ['متوفر', 'منخفض', 'غير متوفر'])->default('متوفر');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parts');
    }
};
