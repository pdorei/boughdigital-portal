<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('encrypted_value')->nullable();
            $table->timestamps();
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->string('storage_disk')->nullable()->after('s3_url');
            $table->string('storage_path')->nullable()->after('storage_disk');
            $table->index(['workspace_id', 'storage_disk']);
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'storage_disk']);
            $table->dropColumn(['storage_disk', 'storage_path']);
        });

        Schema::dropIfExists('application_settings');
    }
};
