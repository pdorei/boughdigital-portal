<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'date_timezone') && ! Schema::hasColumn('users', 'timezone')) {
                $table->renameColumn('date_timezone', 'timezone');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'timezone')) {
                $table->string('timezone')->nullable()->after('avatar_url');
            }

            if (! Schema::hasColumn('users', 'date_format')) {
                $table->string('date_format')->nullable()->after('timezone');
            }

            if (! Schema::hasColumn('users', 'datetime_format')) {
                $table->string('datetime_format')->nullable()->after('date_format');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'datetime_format')) {
                $table->dropColumn('datetime_format');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'timezone') && ! Schema::hasColumn('users', 'date_timezone')) {
                $table->renameColumn('timezone', 'date_timezone');
            }
        });
    }
};
