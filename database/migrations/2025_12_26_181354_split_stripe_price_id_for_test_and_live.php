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
        Schema::table('plans', function (Blueprint $table) {
            $table->renameColumn('stripe_price_id', 'stripe_price_id_live');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->string('stripe_price_id_test')->nullable()->after('stripe_price_id_live');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('stripe_price_id_test');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->renameColumn('stripe_price_id_live', 'stripe_price_id');
        });
    }
};
