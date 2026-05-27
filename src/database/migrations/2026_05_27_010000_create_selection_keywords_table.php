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
        Schema::create('selection_keywords', function (Blueprint $table): void {
            $table->id();
            $table->string('keyword');
            $table->string('type', 16);
            $table->integer('score');
            $table->boolean('enabled')->default(true);
            $table->string('locale', 8)->default('any');
            $table->string('category', 64)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(100);
            $table->timestamps();

            $table->unique(['type', 'keyword']);
            $table->index(['enabled', 'type', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('selection_keywords');
    }
};
