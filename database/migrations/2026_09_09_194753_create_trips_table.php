<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('destination');
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('participant_count');
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('draft');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
