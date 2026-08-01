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
        Schema::table('lunar_collections', function (Blueprint $table) {
            $table->foreignId('restaurant_id')
                ->nullable()
                ->constrained('restaurants')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lunar_collections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('restaurant_id');
        });
    }
};
