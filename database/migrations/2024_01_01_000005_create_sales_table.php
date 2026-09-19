<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique(); // replaces the paper sales invoice
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('user_id')->constrained(); // who processed the sale
            $table->enum('payment_method', ['cash', 'gcash', 'paymaya', 'card'])->default('cash');
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->timestamp('sold_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
