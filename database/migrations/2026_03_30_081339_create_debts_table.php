<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('debts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('credit_card'); // credit_card|personal_loan|car_loan|housing_loan|bnpl|other
            $table->string('name');
            $table->string('currency', 3)->default('MYR');
            $table->unsignedInteger('balance_cents')->default(0);
            $table->unsignedInteger('minimum_payment_cents')->nullable();
            $table->unsignedSmallInteger('apr_bps')->nullable(); // basis points, e.g. 1899 = 18.99%
            $table->unsignedTinyInteger('due_day')->nullable();
            $table->unsignedInteger('credit_limit_cents')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('debts');
    }
};
