<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('notification_email_frequency', 20)->default('weekly')->after('remember_token');
            $table->boolean('notification_immediate_failures')->default(true)->after('notification_email_frequency');
            $table->boolean('notification_immediate_quota')->default(true)->after('notification_immediate_failures');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'notification_email_frequency',
                'notification_immediate_failures',
                'notification_immediate_quota',
            ]);
        });
    }
};
