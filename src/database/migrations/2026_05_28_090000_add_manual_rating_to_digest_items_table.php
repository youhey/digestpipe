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
            $table->tinyInteger('manual_rating')->nullable()->after('analysis_error');
            $table->timestampTz('manual_rated_at')->nullable()->after('manual_rating');

            $table->index(['manual_rating', 'manual_rated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('digest_items', function (Blueprint $table): void {
            $table->dropIndex(['manual_rating', 'manual_rated_at']);
            $table->dropColumn([
                'manual_rating',
                'manual_rated_at',
            ]);
        });
    }
};
