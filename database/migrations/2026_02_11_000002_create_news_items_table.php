<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Shared\Domain\Enum\NewsStatus;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('source_id');
            $table->string('title_original');
            $table->text('content_original');
            $table->string('title_generated')->nullable();
            $table->text('content_translated')->nullable();
            $table->smallInteger('sentiment_score')->default(0);
            $table->jsonb('tags')->nullable();
            $table->boolean('is_important')->default(false);
            $table->string('status')->default(NewsStatus::PROCESSING->value);
            $table->jsonb('source_metadata')->nullable();
            $table->string('raw_fingerprint')->unique();
            $table->string('moderation_reason')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();

            $table->foreign('source_id')->references('id')->on('sources');
        });

        Schema::create('news_vectors', function (Blueprint $table) {
            $table->uuid('news_item_id')->primary();
            $table->binary('embedding');
            $table->foreign('news_item_id')->references('id')->on('news_items')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_vectors');
        Schema::dropIfExists('news_items');
    }
};
