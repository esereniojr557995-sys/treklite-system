<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per product per branch = the "centralized inventory record"
        // described in the Objectives (stock levels tracked per branch,
        // but all retrievable/cross-referenceable from one database).
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'branch_id']); // one stock row per product+branch
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
