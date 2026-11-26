<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-level service connections (Upwork, Anthropic). One row per
 * provider; credentials are encrypted with the application key by the model.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_integrations', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->unique();
            $table->string('status')->default('disconnected');
            $table->text('credentials')->nullable();
            $table->jsonb('settings')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_refreshed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_integrations');
    }
};
