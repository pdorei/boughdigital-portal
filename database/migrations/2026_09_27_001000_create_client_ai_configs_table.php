<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_ai_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('brand_tone')->nullable();
            $table->text('writing_preferences')->nullable();
            $table->text('industry_context')->nullable();
            $table->jsonb('key_urls')->nullable();
            $table->text('custom_instructions')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_ai_configs');
    }
};
