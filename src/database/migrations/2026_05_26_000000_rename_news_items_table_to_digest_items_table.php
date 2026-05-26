<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('news_items') && ! Schema::hasTable('digest_items')) {
            Schema::rename('news_items', 'digest_items');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('digest_items') && ! Schema::hasTable('news_items')) {
            Schema::rename('digest_items', 'news_items');
        }
    }
};
