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
        Schema::create('selection_evaluations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('digest_item_id')->constrained('digest_items')->cascadeOnDelete();
            $table->string('source_key');
            $table->string('phase', 32);
            $table->string('status');
            $table->integer('score');
            $table->string('reason')->nullable();
            $table->jsonb('matched_positive_keywords');
            $table->jsonb('matched_negative_keywords');
            $table->jsonb('input_summary');
            $table->jsonb('selection_config_summary')->nullable();
            $table->timestampTz('evaluated_at');
            $table->timestamps();

            $table->index(['digest_item_id', 'evaluated_at']);
            $table->index(['source_key', 'phase', 'evaluated_at']);
            $table->index(['source_key', 'status', 'evaluated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('selection_evaluations');
    }
};
