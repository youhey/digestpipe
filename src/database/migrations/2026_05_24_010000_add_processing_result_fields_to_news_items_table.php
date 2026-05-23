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
            $table->text('translated_title')->nullable()->after('summary_status');
            $table->text('translated_description')->nullable()->after('translated_title');
            $table->text('summary')->nullable()->after('translated_description');
            $table->text('processing_error')->nullable()->after('summary');
            $table->timestampTz('translation_started_at')->nullable()->after('processing_error');
            $table->timestampTz('translation_completed_at')->nullable()->after('translation_started_at');
            $table->timestampTz('summary_started_at')->nullable()->after('translation_completed_at');
            $table->timestampTz('summary_completed_at')->nullable()->after('summary_started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('news_items', function (Blueprint $table): void {
            $table->dropColumn([
                'translated_title',
                'translated_description',
                'summary',
                'processing_error',
                'translation_started_at',
                'translation_completed_at',
                'summary_started_at',
                'summary_completed_at',
            ]);
        });
    }
};
