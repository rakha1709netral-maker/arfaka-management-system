<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_assignments', function (Blueprint $table): void {
            $table->string('status', 20)->default('accepted');
            $table->text('response_note')->nullable();
            $table->timestamp('responded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('trip_assignments', function (Blueprint $table): void {
            $table->dropColumn(['status', 'response_note', 'responded_at']);
        });
    }
};
