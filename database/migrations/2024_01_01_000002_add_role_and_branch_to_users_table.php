<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // owner   -> top authority, full access, all branches
            // manager -> one level below Owner, full access, all branches
            //            (per Chapter 1: assists the Owner and is authorized
            //            to act on the Owner's behalf when needed)
            // staff   -> limited access, tied to one branch (Matina)
            $table->enum('role', ['owner', 'manager', 'staff'])->default('staff')->after('email');
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
