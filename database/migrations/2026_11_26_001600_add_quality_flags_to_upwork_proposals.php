<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A fixed-price job without a budget can be approved by a person, and its bid
 * is then set by hand, so bid_amount may be empty until approval. Quality
 * flags record cover-letter checks (length, contact details, ranking promises).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('upwork_proposals', function (Blueprint $table) {
            $table->decimal('bid_amount', 12, 2)->nullable()->change();
            $table->jsonb('quality_flags')->nullable()->after('screening_answers');
        });
    }

    public function down(): void
    {
        Schema::table('upwork_proposals', function (Blueprint $table) {
            $table->dropColumn('quality_flags');
        });
    }
};
