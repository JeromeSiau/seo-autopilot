<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Only add columns if they don't already exist
        if (! Schema::hasColumn('teams', 'plan_id')) {
            Schema::table('teams', function (Blueprint $table) {
                $table->foreignId('plan_id')->nullable()->after('owner_id')->constrained();
            });
        }
        if (! Schema::hasColumn('teams', 'is_trial')) {
            Schema::table('teams', function (Blueprint $table) {
                $table->boolean('is_trial')->default(true)->after('plan_id');
            });
        }
        // trial_ends_at already exists in the original teams table migration
    }

    public function down(): void
    {
        if (Schema::hasColumn('teams', 'plan_id')) {
            Schema::table('teams', function (Blueprint $table) {
                $table->dropConstrainedForeignId('plan_id');
            });
        }
        if (Schema::hasColumn('teams', 'is_trial')) {
            Schema::table('teams', function (Blueprint $table) {
                $table->dropColumn(['is_trial']);
            });
        }
    }
};
