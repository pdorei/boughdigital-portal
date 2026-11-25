<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_pillars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('color', 7)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['client_id', 'name']);
            $table->index(['client_id', 'sort_order']);
        });

        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            // Pillars in use cannot be deleted (ContentPillarService); nullOnDelete
            // only matters when the whole client is removed.
            $table->foreignId('content_pillar_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_writer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->nullable();
            $table->string('primary_keyword')->nullable();
            $table->jsonb('secondary_keywords')->nullable();
            $table->string('search_intent')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedInteger('word_count')->nullable();
            $table->text('content_body')->nullable();
            $table->text('excerpt')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->text('canonical_url')->nullable();
            $table->text('published_url')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->jsonb('tags')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'status']);
        });

        Schema::create('content_calendar_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_pillar_id')->nullable()->constrained()->nullOnDelete();
            // The article produced for this entry. Deleting the article keeps the plan.
            $table->foreignId('article_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('assigned_writer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('target_keyword')->nullable();
            $table->jsonb('secondary_keywords')->nullable();
            $table->string('status')->default('planned');
            $table->string('content_type')->default('article');
            $table->string('search_intent')->nullable();
            $table->date('due_date')->nullable();
            $table->date('publish_date')->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('performance_data')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'publish_date']);
            $table->index(['client_id', 'status']);
        });

        Schema::create('article_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scored_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('score_type');
            $table->unsignedSmallInteger('overall_score');
            $table->jsonb('category_scores')->nullable();
            $table->text('feedback')->nullable();
            $table->timestamps();

            $table->index(['article_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_scores');
        Schema::dropIfExists('content_calendar_entries');
        Schema::dropIfExists('articles');
        Schema::dropIfExists('content_pillars');
    }
};
