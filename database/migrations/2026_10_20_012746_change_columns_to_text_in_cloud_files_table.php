<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cloud_files', function (Blueprint $table) {
            // Upgrade columns from VARCHAR(255) to TEXT to handle long titles/links
            $table->text('name')->change();
            $table->text('view_link')->change();
        });
    }

    public function down(): void
    {
        Schema::table('cloud_files', function (Blueprint $table) {
            // Fallback rules if rolled back
            $table->string('name', 255)->change();
            $table->string('view_link', 255)->change();
        });
    }
};
