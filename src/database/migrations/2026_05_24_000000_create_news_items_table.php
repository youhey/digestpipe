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
            $table->text('title');
            $table->text('excerpt')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('fetched_at');
            $table->string('content_hash', 64);
            $table->string('processing_status')->default('fetched');
            $table->string('translation_status')->default('pending');
            $table->string('summary_status')->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['source_key', 'identity_hash']);
            $table->index(['processing_status', 'translation_status', 'summary_status']);
            $table->index('published_at');
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
