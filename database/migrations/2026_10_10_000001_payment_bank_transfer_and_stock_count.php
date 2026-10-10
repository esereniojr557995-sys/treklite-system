<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - Payment methods become Cash, GCash and Bank Transfer (the column becomes a plain string).
 * - Stock movements get a "count" type and remember the quantity BEFORE the change,
 *   so a physical count is traced (system quantity -> counted quantity) instead of overwriting history.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE sales MODIFY payment_method VARCHAR(20) NOT NULL DEFAULT 'cash'");
            DB::statement("ALTER TABLE stock_movements MODIFY type VARCHAR(20) NOT NULL");
        }

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->integer('quantity_before')->nullable()->after('type');
        });

        // Old physical-count rows were called "adjust"; they are counts.
        DB::table('stock_movements')->where('type', 'adjust')->update(['type' => 'count']);
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn('quantity_before');
        });
    }
};
