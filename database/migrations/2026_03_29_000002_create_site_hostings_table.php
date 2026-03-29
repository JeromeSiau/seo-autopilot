<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_hostings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('staging_domain')->nullable()->unique();
            $table->string('custom_domain')->nullable()->unique();
            $table->string('canonical_domain')->nullable();
            $table->string('domain_status', 30)->default('none');
            $table->string('ssl_status', 30)->default('none');
            $table->string('template_key', 30)->default('editorial');
            $table->json('theme_settings')->nullable();
            $table->string('ploi_tenant_id')->nullable();
            $table->timestamp('staging_certificate_requested_at')->nullable();
            $table->timestamp('custom_domain_verified_at')->nullable();
            $table->timestamp('custom_certificate_requested_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_exported_at')->nullable();
            $table->timestamps();

            $table->index(['domain_status', 'ssl_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_hostings');
    }
};
