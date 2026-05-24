<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('news_items', function (Blueprint $table): void {
            $table->id();
            $table->string('source_key');
            $table->string('source_name');
            $table->text('external_id')->nullable();
            $table->string('identity_hash', 64);
            $table->text('source_url')->nullable();
            $table->text('discussion_url')->nullable();
            $table->text('title');
            $table->text('excerpt')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('fetched_at');
            $table->string('content_hash', 64);
            $table->string('article_content_status')->default('pending');
            $table->text('article_content_text')->nullable();
            $table->timestampTz('article_content_fetched_at')->nullable();
            $table->text('article_content_error')->nullable();
            $table->string('analysis_status')->default('pending');
            $table->jsonb('analysis_json')->nullable();
            $table->string('analysis_model')->nullable();
            $table->text('analysis_error')->nullable();
            $table->timestampTz('analyzed_at')->nullable();
            $table->timestamps();

            $table->unique(['source_key', 'identity_hash']);
            $table->index('published_at');
            $table->index(['source_key', 'article_content_status']);
            $table->index(['source_key', 'analysis_status']);
            $table->index(['analysis_status', 'analyzed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('news_items');
    }
};
