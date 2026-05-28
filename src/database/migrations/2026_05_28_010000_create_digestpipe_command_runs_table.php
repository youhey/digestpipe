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
        Schema::create('digestpipe_command_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('command_name');
            $table->jsonb('command_arguments');
            $table->string('status', 32);
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->jsonb('result_summary')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['command_name', 'started_at']);
            $table->index(['status', 'started_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('digestpipe_command_runs');
    }
};
