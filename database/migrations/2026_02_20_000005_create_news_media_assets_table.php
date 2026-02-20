<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_media_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('news_item_id')
                ->constrained('news_items')
                ->cascadeOnDelete();
            $table->string('slot', 32);
            $table->unsignedInteger('position')->default(0);
            $table->string('source_url', 2048)->nullable();
            $table->string('source_mime_type', 191)->nullable();
            $table->string('local_disk', 64)->default('public');
            $table->string('local_path', 2048)->nullable();
            $table->string('downloaded_mime_type', 191)->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->char('checksum_sha256', 64)->nullable();
            $table->string('download_status', 32)->default('pending');
            $table->text('last_error')->nullable();
            $table->timestampTz('downloaded_at')->nullable();
            $table->timestampsTz();

            $table->unique(['news_item_id', 'slot', 'position'], 'news_media_assets_slot_unique');
            $table->index(['news_item_id', 'download_status'], 'news_media_assets_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_media_assets');
    }
};
