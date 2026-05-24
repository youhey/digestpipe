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
        Schema::table('news_items', function (Blueprint $table): void {
            $table->text('discussion_url')->nullable()->after('source_url');
            $table->string('article_content_status')->default('pending')->after('summary_status');
            $table->text('article_content_text')->nullable()->after('article_content_status');
            $table->timestampTz('article_content_fetched_at')->nullable()->after('article_content_text');
            $table->text('article_content_error')->nullable()->after('article_content_fetched_at');

            $table->index(['source_key', 'article_content_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('news_items', function (Blueprint $table): void {
            $table->dropIndex(['source_key', 'article_content_status']);
            $table->dropColumn([
                'discussion_url',
                'article_content_status',
                'article_content_text',
                'article_content_fetched_at',
                'article_content_error',
            ]);
        });
    }
};
