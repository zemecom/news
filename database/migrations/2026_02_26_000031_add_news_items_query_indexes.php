<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_items', function (Blueprint $table): void {
            $table->index(
                ['source_id', 'status', 'published_at', 'id'],
                'news_items_source_status_feed_idx',
            );
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE INDEX IF NOT EXISTS news_items_tags_gin_idx ON news_items USING GIN (tags jsonb_path_ops)');
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE INDEX IF NOT EXISTS news_items_title_original_trgm_idx ON news_items USING GIN (title_original gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS news_items_title_generated_trgm_idx ON news_items USING GIN (title_generated gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::table('news_items', function (Blueprint $table): void {
            $table->dropIndex('news_items_source_status_feed_idx');
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS news_items_tags_gin_idx');
        DB::statement('DROP INDEX IF EXISTS news_items_title_original_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS news_items_title_generated_trgm_idx');
    }
};
