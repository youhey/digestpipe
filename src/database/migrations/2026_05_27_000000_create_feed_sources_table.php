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
        Schema::create('feed_sources', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('name');
            $table->string('url', 2048);
            $table->string('language', 8);
            $table->boolean('enabled');
            $table->boolean('analysis_enabled');
            $table->string('tier', 32);
            $table->string('category', 64);
            $table->unsignedInteger('sort_order')->default(100);
            $table->timestamps();

            $table->index(['enabled', 'sort_order']);
            $table->index(['enabled', 'analysis_enabled', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feed_sources');
    }
};
