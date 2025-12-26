<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL: modify ENUM directly
        // SQLite: column is already varchar, no modification needed
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE integrations MODIFY COLUMN type ENUM('wordpress', 'webflow', 'shopify', 'ghost') NOT NULL");
        }
        // For SQLite, the enum is stored as varchar and accepts any string value
        // Validation is handled at the application level
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE integrations MODIFY COLUMN type ENUM('wordpress', 'webflow', 'shopify') NOT NULL");
        }
    }
};
