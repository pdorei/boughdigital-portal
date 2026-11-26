<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each keyword is now searched once per run and searches A/B/C are applied in
 * code, so a run records which of them each job matched. New jobs that pass
 * the hard rules get one job-details call; calls are counted by kind.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('upwork_runs', function (Blueprint $table) {
            $table->unsignedInteger('search_calls')->default(0)->after('api_calls');
            $table->unsignedInteger('detail_calls')->default(0)->after('search_calls');
            $table->jsonb('search_matches')->nullable()->after('jobs_new');
        });

        Schema::table('upwork_jobs', function (Blueprint $table) {
            $table->timestamp('details_fetched_at')->nullable()->after('screening_questions');
        });
    }

    public function down(): void
    {
        Schema::table('upwork_jobs', function (Blueprint $table) {
            $table->dropColumn('details_fetched_at');
        });

        Schema::table('upwork_runs', function (Blueprint $table) {
            $table->dropColumn(['search_calls', 'detail_calls', 'search_matches']);
        });
    }
};
