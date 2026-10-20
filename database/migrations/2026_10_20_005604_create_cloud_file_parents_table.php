<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cloud_file_parents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('integration_id')->constrained()->onDelete('cascade');

            // Map the file ID to its parent folder ID using the provider's string tokens
            $table->string('provider_file_id');
            $table->string('provider_parent_id');

            $table->timestamps();

            // Index everything for microsecond-fast directory folder traversal joins
            $table->unique(['integration_id', 'provider_file_id', 'provider_parent_id'], 'file_parent_mapping_unique');
            $table->index(['integration_id', 'provider_parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloud_file_parents');
    }
};
