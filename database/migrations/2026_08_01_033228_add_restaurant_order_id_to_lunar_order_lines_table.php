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
        Schema::table('lunar_order_lines', function (Blueprint $table) {
            $table->foreignId('restaurant_order_id')
                ->nullable()
                ->constrained('restaurant_orders')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lunar_order_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('restaurant_order_id');
        });
    }
};
