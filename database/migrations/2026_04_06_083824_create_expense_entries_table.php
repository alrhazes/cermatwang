<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('spent_at');
            $table->string('year_month', 7);
            $table->string('category');
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('MYR');
            $table->string('place_label')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('location_accuracy_m')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'year_month']);
            $table->index(['user_id', 'spent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_entries');
    }
};
