<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trip_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('leader_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('type', 30);
            $table->string('category', 30)->nullable();
            $table->string('status', 20)->default('pending');
            $table->date('occurred_on');
            $table->unsignedBigInteger('amount');
            $table->string('description', 500);
            $table->string('receipt_path')->nullable();
            $table->uuid('submission_token')->unique();
            $table->timestamps();
            $table->index(['trip_id', 'status', 'type']);
            $table->index(['status', 'occurred_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_entries');
    }
};
