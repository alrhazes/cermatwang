<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_budget_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('year_month', 7);
            $table->string('category');
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('MYR');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'year_month', 'category']);
            $table->index(['user_id', 'year_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_budget_allocations');
    }
};
