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
        Schema::table('income_sources', function (Blueprint $table) {
            $table->unique(['user_id', 'name']);
        });

        Schema::table('commitments', function (Blueprint $table) {
            $table->unique(['user_id', 'name']);
        });

        Schema::table('debts', function (Blueprint $table) {
            $table->unique(['user_id', 'type', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('income_sources', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'name']);
        });

        Schema::table('commitments', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'name']);
        });

        Schema::table('debts', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'type', 'name']);
        });
    }
};
