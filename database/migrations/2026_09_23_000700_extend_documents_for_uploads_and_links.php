<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // google_doc_url becomes the generic link column for Google Docs and
        // other external documents; existing values are kept by the rename.
        Schema::table('documents', function (Blueprint $table) {
            $table->renameColumn('google_doc_url', 'external_url');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->text('external_url')->nullable()->change();
            $table->string('provider')->default('upload')->change();
            $table->string('original_name')->nullable()->after('storage_path');
            $table->string('mime_type')->nullable()->after('original_name');
            $table->unsignedBigInteger('size_bytes')->nullable()->after('mime_type');
        });

        DB::table('documents')->where('provider', 'manual')->whereNotNull('external_url')->update(['provider' => 'external_link']);
        DB::table('documents')->where('provider', 'manual')->update(['provider' => 'upload']);

        // Folders are now a fixed list (see App\Enums\DocumentFolder).
        DB::table('documents')
            ->whereNotIn('folder', ['seo', 'strategy', 'content', 'reports', 'other'])
            ->update(['folder' => 'other']);

        // s3_url was never written by the application; uploads are addressed by
        // storage_disk + storage_path. Only drop it when no row relies on it.
        if (DB::table('documents')->whereNotNull('s3_url')->doesntExist()) {
            Schema::table('documents', function (Blueprint $table) {
                $table->dropColumn('s3_url');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('documents', 's3_url')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->string('s3_url')->nullable();
            });
        }

        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['original_name', 'mime_type', 'size_bytes']);
            $table->string('provider')->default('manual')->change();
            $table->renameColumn('external_url', 'google_doc_url');
        });
    }
};
