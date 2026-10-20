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
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
            $table->timestamp('password_set_at')->nullable()->after('password');
        });

        DB::table('users')->whereNotNull('password')->update(['password_set_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('users')->whereNull('password')->exists()) {
            throw new RuntimeException('Pending accounts must complete onboarding before reverting this migration.');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('password_set_at');
            $table->string('password')->nullable(false)->change();
        });
    }
};
