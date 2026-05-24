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
            $table->string('analysis_status')->default('pending')->after('summary_status');
            $table->jsonb('analysis_json')->nullable()->after('analysis_status');
            $table->string('analysis_model')->nullable()->after('analysis_json');
            $table->text('analysis_error')->nullable()->after('analysis_model');
            $table->timestampTz('analyzed_at')->nullable()->after('analysis_error');

            $table->index(['source_key', 'analysis_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('news_items', function (Blueprint $table): void {
            $table->dropIndex(['source_key', 'analysis_status']);
            $table->dropColumn([
                'analysis_status',
                'analysis_json',
                'analysis_model',
                'analysis_error',
                'analyzed_at',
            ]);
        });
    }
};
