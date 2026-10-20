<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cloud_files', function (Blueprint $table) {
            $table->id();
            // Connects the file back to the integration row and owner user
            $table->foreignId('integration_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Unique tracking attributes
            $table->string('provider_file_id'); // e.g. Google's alphanumeric ID string
            $table->string('name');
            $table->string('mime_type')->nullable(); // Helps us identify file vs folder
            $table->unsignedBigInteger('size')->nullable(); // In bytes
            $table->text('view_link')->nullable(); // Direct preview target string
            $table->boolean('is_folder')->default(false);

            $table->timestamps();

            // Prevent duplicate listings for the same file under an integration account
            $table->unique(['integration_id', 'provider_file_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloud_files');
    }
};
