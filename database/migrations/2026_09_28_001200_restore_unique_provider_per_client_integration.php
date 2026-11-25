<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The "other" provider was removed, so every provider is limited to one
        // integration per client again. No "other" rows were ever stored.
        if (DB::table('client_integrations')->where('provider', 'other')->exists()) {
            throw new RuntimeException('client_integrations still has provider "other" rows; resolve them before migrating.');
        }

        DB::statement('drop index if exists client_integrations_client_provider_unique');

        Schema::table('client_integrations', function (Blueprint $table) {
            $table->unique(['client_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::table('client_integrations', function (Blueprint $table) {
            $table->dropUnique(['client_id', 'provider']);
        });

        DB::statement("create unique index client_integrations_client_provider_unique on client_integrations (client_id, provider) where provider <> 'other'");
    }
};
