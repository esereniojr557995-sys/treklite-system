<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Split payments: a sale can be partly cash and partly GCash / bank transfer.
     * amount_tendered = cash received, digital_amount = the part paid by GCash / bank transfer.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('digital_amount', 10, 2)->default(0)->after('amount_tendered');
        });

        // Older GCash / bank / other non-cash sales were paid fully by that method.
        DB::table('sales')->where('payment_method', '!=', 'cash')->update([
            'digital_amount'  => DB::raw('total_amount'),
            'amount_tendered' => 0,
            'change_amount'   => 0,
        ]);
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('digital_amount');
        });
    }
};
