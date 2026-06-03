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
        Schema::table('digest_items', function (Blueprint $table): void {
            $table->timestampTz('article_content_queued_at')->nullable()->after('article_content_status');
            $table->timestampTz('article_content_started_at')->nullable()->after('article_content_queued_at');
            $table->timestampTz('article_content_skipped_at')->nullable()->after('article_content_fetched_at');
            $table->timestampTz('article_content_failed_at')->nullable()->after('article_content_skipped_at');
            $table->timestampTz('analysis_queued_at')->nullable()->after('analysis_status');
            $table->timestampTz('analysis_started_at')->nullable()->after('analysis_queued_at');
            $table->timestampTz('analysis_completed_at')->nullable()->after('analyzed_at');
            $table->timestampTz('analysis_skipped_at')->nullable()->after('analysis_completed_at');
            $table->timestampTz('analysis_failed_at')->nullable()->after('analysis_skipped_at');

            $table->index(['article_content_status', 'article_content_queued_at'], 'digest_items_content_queue_idx');
            $table->index(['analysis_status', 'analysis_queued_at'], 'digest_items_analysis_queue_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('digest_items', function (Blueprint $table): void {
            $table->dropIndex('digest_items_content_queue_idx');
            $table->dropIndex('digest_items_analysis_queue_idx');

            $table->dropColumn([
                'article_content_queued_at',
                'article_content_started_at',
                'article_content_skipped_at',
                'article_content_failed_at',
                'analysis_queued_at',
                'analysis_started_at',
                'analysis_completed_at',
                'analysis_skipped_at',
                'analysis_failed_at',
            ]);
        });
    }
};
