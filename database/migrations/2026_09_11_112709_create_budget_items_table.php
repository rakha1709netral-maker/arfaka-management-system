<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trip_id')->constrained()->restrictOnDelete();
            $table->string('category', 30);
            $table->string('description', 255);
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_amount');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_items');
    }
};
