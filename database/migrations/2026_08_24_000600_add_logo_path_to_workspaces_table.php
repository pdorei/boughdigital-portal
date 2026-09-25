<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('workspaces', 'logo_path')) {
            return;
        }

        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('logo_path', 500)->nullable()->after('logo_url');
        });

        DB::table('workspaces')
            ->whereNull('logo_path')
            ->where('logo_url', 'like', 'workspaces/%')
            ->update(['logo_path' => DB::raw('logo_url')]);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('workspaces', 'logo_path')) {
            return;
        }

        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('logo_path');
        });
    }
};
