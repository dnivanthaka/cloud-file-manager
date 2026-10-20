<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            // Link to the user table
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Metadata to distinguish accounts
            $table->string('provider'); // 'google'
            $table->string('account_email'); // e.g. "work@gmail.com"
            $table->string('account_name')->nullable(); // e.g. "John Work"

            // Encrypted token blocks
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            // expiry of access token
            $table->integer('expires_at');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};
