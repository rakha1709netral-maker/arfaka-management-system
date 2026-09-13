<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_entries', function (Blueprint $table): void {
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('review_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('location_accuracy', 8, 2)->nullable();
            $table->unsignedBigInteger('receipt_original_size')->nullable();
            $table->unsignedBigInteger('receipt_stored_size')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('finance_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn([
                'review_reason', 'reviewed_at', 'captured_at', 'latitude', 'longitude',
                'location_accuracy', 'receipt_original_size', 'receipt_stored_size',
            ]);
        });
    }
};
