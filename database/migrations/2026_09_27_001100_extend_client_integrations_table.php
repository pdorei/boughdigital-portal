<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The table was created with the core schema but never written to by the
        // application. last_verified_at is kept because the admin screens read it.
        Schema::table('client_integrations', function (Blueprint $table) {
            $table->renameColumn('encrypted_credentials', 'credentials');
            $table->renameColumn('metadata', 'settings');
        });

        Schema::table('client_integrations', function (Blueprint $table) {
            $table->string('auth_type')->default('other')->after('provider');
            $table->string('name')->nullable()->after('auth_type');
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->text('last_error')->nullable();

            $table->dropUnique(['client_id', 'provider']);
        });

        // One integration per named provider; "other" may hold several services.
        DB::statement("create unique index client_integrations_client_provider_unique on client_integrations (client_id, provider) where provider <> 'other'");
    }

    public function down(): void
    {
        DB::statement('drop index if exists client_integrations_client_provider_unique');

        Schema::table('client_integrations', function (Blueprint $table) {
            $table->dropColumn(['auth_type', 'name', 'connected_at', 'last_used_at', 'last_error']);
            $table->renameColumn('credentials', 'encrypted_credentials');
            $table->renameColumn('settings', 'metadata');
        });

        Schema::table('client_integrations', function (Blueprint $table) {
            $table->unique(['client_id', 'provider']);
        });
    }
};
