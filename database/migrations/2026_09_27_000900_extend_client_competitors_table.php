<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The table was created with the core schema but never used by the
        // application. Renames keep any existing values.
        Schema::table('client_competitors', function (Blueprint $table) {
            $table->renameColumn('name', 'competitor_name');
            $table->renameColumn('domain', 'competitor_domain');
        });

        Schema::table('client_competitors', function (Blueprint $table) {
            $table->text('description')->nullable()->after('competitor_domain');
            $table->string('industry')->nullable()->after('description');
            $table->text('notes')->nullable()->after('industry');
            $table->jsonb('contact')->nullable()->after('notes');
            $table->jsonb('social_media')->nullable()->after('contact');
            $table->jsonb('keywords')->nullable()->after('social_media');
            $table->string('logo_disk')->nullable()->after('keywords');
            $table->string('logo_path')->nullable()->after('logo_disk');
            $table->string('location')->nullable()->after('logo_path');
            $table->string('competitor_type')->nullable()->after('location');
            $table->string('tier')->default('primary')->after('competitor_type');

            $table->unique(['client_id', 'competitor_domain']);
        });
    }

    public function down(): void
    {
        Schema::table('client_competitors', function (Blueprint $table) {
            $table->dropUnique(['client_id', 'competitor_domain']);
            $table->dropColumn([
                'description', 'industry', 'notes', 'contact', 'social_media', 'keywords',
                'logo_disk', 'logo_path', 'location', 'competitor_type', 'tier',
            ]);
        });

        Schema::table('client_competitors', function (Blueprint $table) {
            $table->renameColumn('competitor_name', 'name');
            $table->renameColumn('competitor_domain', 'domain');
        });
    }
};
