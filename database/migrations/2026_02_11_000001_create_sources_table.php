<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sources', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('url');
            $table->string('type')->default('rss');
            $table->string('language_default')->nullable();
            $table->string('cron_expression')->nullable();
            $table->boolean('is_active')->default(true);
            $table->jsonb('retry_backoff_state')->nullable();
            $table->timestampTz('last_success_at')->nullable();
            $table->timestampTz('last_error_at')->nullable();
            $table->unsignedSmallInteger('error_streak')->default(0);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sources');
    }
};
