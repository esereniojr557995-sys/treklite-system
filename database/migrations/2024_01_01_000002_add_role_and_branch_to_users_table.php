<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // owner        -> full access, all branches
            // co_owner     -> full access, all branches (per Chapter 1: acts as owner's support system)
            // staff        -> limited access, tied to one branch (Matina or Malita)
            $table->enum('role', ['owner', 'co_owner', 'staff'])->default('staff')->after('email');
            $table->foreignId('branch_id')->nullable()->after('role')
                ->constrained('branches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn('role');
        });
    }
};
