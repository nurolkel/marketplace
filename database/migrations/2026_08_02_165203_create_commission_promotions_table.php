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
        Schema::create('commission_promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            // Promotional commission rate in basis points (0 = commission-free).
            $table->unsignedSmallInteger('rate')->default(0);
            // Promotion expires this many days after assignment; null = no time limit.
            $table->unsignedSmallInteger('duration_days')->nullable();
            // Promotion ends after this many fulfilled sub-orders; null = no order cap.
            $table->unsignedInteger('max_orders')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commission_promotions');
    }
};
