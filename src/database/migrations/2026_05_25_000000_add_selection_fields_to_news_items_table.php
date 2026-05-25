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
            $table->string('selection_status')->default('pending')->after('content_hash');
            $table->integer('selection_score')->nullable()->after('selection_status');
            $table->string('selection_reason')->nullable()->after('selection_score');
            $table->jsonb('selection_result')->nullable()->after('selection_reason');
            $table->timestampTz('selection_evaluated_at')->nullable()->after('selection_result');

            $table->index(['source_key', 'selection_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('news_items', function (Blueprint $table): void {
            $table->dropIndex(['source_key', 'selection_status']);
            $table->dropColumn([
                'selection_status',
                'selection_score',
                'selection_reason',
                'selection_result',
                'selection_evaluated_at',
            ]);
        });
    }
};
