<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Upwork proposal automation (Super Admin, platform level). Jobs and proposals
 * are never deleted: rejecting changes the status, so the history can be used
 * to tune the rules.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('upwork_runs', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('running');
            $table->string('trigger')->default('schedule');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('api_calls')->default(0);
            $table->unsignedInteger('jobs_found')->default(0);
            $table->unsignedInteger('jobs_new')->default(0);
            $table->jsonb('errors')->nullable();
            $table->unsignedBigInteger('claude_tokens_used')->default(0);
            $table->timestamps();

            $table->index('started_at');
        });

        Schema::create('upwork_jobs', function (Blueprint $table) {
            $table->id();
            // Reserved for a future per-workspace pipeline; null means platform level.
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('upwork_run_id')->nullable()->constrained('upwork_runs')->nullOnDelete();
            $table->string('upwork_job_id')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('track');
            $table->string('search_key', 1)->nullable();
            $table->string('keyword')->nullable();
            $table->string('job_type');
            $table->decimal('hourly_min', 10, 2)->nullable();
            $table->decimal('hourly_max', 10, 2)->nullable();
            $table->decimal('fixed_budget', 12, 2)->nullable();
            $table->string('experience_level')->nullable();
            $table->string('duration')->nullable();
            $table->jsonb('skills')->nullable();
            $table->unsignedInteger('proposals_count')->nullable();
            $table->string('client_country')->nullable();
            $table->boolean('client_payment_verified')->nullable();
            $table->decimal('client_total_spent', 14, 2)->nullable();
            $table->decimal('client_hire_rate', 5, 2)->nullable();
            $table->decimal('client_rating', 3, 2)->nullable();
            $table->unsignedInteger('client_reviews_count')->nullable();
            $table->jsonb('screening_questions')->nullable();
            $table->jsonb('raw')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('first_seen_at');
            $table->string('status')->default('new');
            $table->timestamps();

            $table->index(['status', 'posted_at']);
            $table->index(['track', 'status']);
            $table->index('first_seen_at');
        });

        Schema::create('upwork_job_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('upwork_job_id')->constrained('upwork_jobs')->cascadeOnDelete();
            $table->string('stage');
            $table->string('rule_result')->nullable();
            $table->unsignedTinyInteger('fit_score')->nullable();
            $table->string('outcome');
            $table->text('reason')->nullable();
            $table->jsonb('red_flags')->nullable();
            $table->jsonb('analysis')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at');
            $table->timestamps();

            $table->index(['upwork_job_id', 'decided_at']);
        });

        Schema::create('upwork_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('upwork_job_id')->unique()->constrained('upwork_jobs')->cascadeOnDelete();
            $table->string('bid_type');
            $table->decimal('bid_amount', 12, 2);
            $table->text('cover_letter')->nullable();
            $table->jsonb('screening_answers')->nullable();
            $table->timestamp('target_send_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedSmallInteger('connects_spent')->nullable();
            $table->boolean('edited')->default(false);
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'target_send_at']);
            $table->index('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upwork_proposals');
        Schema::dropIfExists('upwork_job_decisions');
        Schema::dropIfExists('upwork_jobs');
        Schema::dropIfExists('upwork_runs');
    }
};
