<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_provider_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('provider', 50);
            $table->string('display_name', 120);
            $table->boolean('is_enabled')->default(true);
            $table->string('codex_home_subpath', 120)->unique();
            $table->string('default_model', 120);
            $table->unsignedSmallInteger('max_parallel_jobs')->default(1);
            $table->string('auth_status', 40)->default('not_authenticated');
            $table->string('auth_mode', 40)->nullable();
            $table->string('login_id', 120)->nullable();
            $table->text('auth_url')->nullable();
            $table->string('account_email')->nullable();
            $table->string('plan_type', 40)->nullable();
            $table->jsonb('rate_limit_snapshot')->nullable();
            $table->timestampTz('last_status_checked_at')->nullable();
            $table->timestampTz('last_authenticated_at')->nullable();
            $table->timestampTz('last_error_at')->nullable();
            $table->text('last_error_message')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_provider_accounts');
    }
};
