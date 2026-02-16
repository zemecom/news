<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('news_items', function (Blueprint $table) {
            $table->index(['status', 'published_at', 'id'], 'news_items_feed_idx');
            $table->index(['source_id', 'status'], 'news_items_source_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('news_items', function (Blueprint $table) {
            $table->dropIndex('news_items_feed_idx');
            $table->dropIndex('news_items_source_status_idx');
        });
    }
};
