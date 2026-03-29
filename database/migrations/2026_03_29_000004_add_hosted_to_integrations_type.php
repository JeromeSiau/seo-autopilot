<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE integrations MODIFY COLUMN type ENUM('wordpress', 'webflow', 'shopify', 'ghost', 'hosted') NOT NULL");
            return;
        }

        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        Schema::disableForeignKeyConstraints();
        Schema::rename('integrations', 'integrations_old');
        DB::statement('DROP INDEX IF EXISTS integrations_team_id_type_index');
        DB::statement('DROP INDEX IF EXISTS integrations_site_id_unique');

        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->cascadeOnDelete();
            $table->enum('type', ['wordpress', 'webflow', 'shopify', 'ghost', 'hosted']);
            $table->string('name');
            $table->text('credentials');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'type']);
            $table->unique('site_id');
        });

        DB::table('integrations_old')
            ->orderBy('id')
            ->get()
            ->each(fn (object $row) => DB::table('integrations')->insert((array) $row));

        Schema::drop('integrations_old');
        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE integrations MODIFY COLUMN type ENUM('wordpress', 'webflow', 'shopify', 'ghost') NOT NULL");
            return;
        }

        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        Schema::disableForeignKeyConstraints();
        Schema::rename('integrations', 'integrations_old');
        DB::statement('DROP INDEX IF EXISTS integrations_team_id_type_index');
        DB::statement('DROP INDEX IF EXISTS integrations_site_id_unique');

        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->cascadeOnDelete();
            $table->enum('type', ['wordpress', 'webflow', 'shopify', 'ghost']);
            $table->string('name');
            $table->text('credentials');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'type']);
            $table->unique('site_id');
        });

        DB::table('integrations_old')
            ->where('type', '!=', 'hosted')
            ->orderBy('id')
            ->get()
            ->each(fn (object $row) => DB::table('integrations')->insert((array) $row));

        Schema::drop('integrations_old');
        Schema::enableForeignKeyConstraints();
    }
};
