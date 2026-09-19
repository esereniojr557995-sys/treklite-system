<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();     // simple product code
            $table->string('name');
            $table->string('category')->nullable(); // e.g. Slippers, Apparel, Textiles
            $table->decimal('price', 10, 2);
            $table->integer('low_stock_threshold')->default(5); // for low-stock alerts
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
