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
        Schema::table('restaurants', function (Blueprint $table) {
            // Tier on the sliding commission scale; null falls back to
            // the platform's default tier.
            $table->foreignId('commission_tier_id')->nullable()->after('status')
                ->constrained()->nullOnDelete();
            // Full exception: a custom rate in basis points that beats
            // any tier. Null means the tier's rate applies.
            $table->unsignedSmallInteger('commission_rate')->nullable()->after('commission_tier_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('commission_tier_id');
            $table->dropColumn('commission_rate');
        });
    }
};
