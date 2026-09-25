<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Recording the disk per logo keeps existing URLs valid after the
        // platform storage driver is switched (for example local -> S3).
        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('logo_disk', 32)->nullable()->after('logo_path');
        });

        DB::table('workspaces')
            ->whereNotNull('logo_path')
            ->update(['logo_disk' => 'public']);

        // The manual logo URL field was retired in favour of uploads.
        if (DB::table('workspaces')->whereNotNull('logo_url')->doesntExist()) {
            Schema::table('workspaces', function (Blueprint $table) {
                $table->dropColumn('logo_url');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('workspaces', 'logo_url')) {
            Schema::table('workspaces', function (Blueprint $table) {
                $table->string('logo_url')->nullable();
            });
        }

        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('logo_disk');
        });
    }
};
