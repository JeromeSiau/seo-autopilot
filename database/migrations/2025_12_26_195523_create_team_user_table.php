<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('team_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('member'); // owner, admin, member
            $table->timestamps();

            $table->unique(['team_id', 'user_id']);
        });

        // Migrate existing team_id data to pivot table
        $now = DB::getDriverName() === 'sqlite' ? "datetime('now')" : 'NOW()';
        DB::statement("
            INSERT INTO team_user (team_id, user_id, role, created_at, updated_at)
            SELECT team_id, id, 'member', {$now}, {$now}
            FROM users
            WHERE team_id IS NOT NULL
        ");

        // Keep team_id as current_team_id for now (user's active team)
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('team_id', 'current_team_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('current_team_id', 'team_id');
        });

        Schema::dropIfExists('team_user');
    }
};
