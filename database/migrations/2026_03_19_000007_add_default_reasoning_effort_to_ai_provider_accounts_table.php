<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_provider_accounts', function (Blueprint $table) {
            $table->string('default_reasoning_effort', 20)->nullable()->after('default_model');
        });
    }

    public function down(): void
    {
        Schema::table('ai_provider_accounts', function (Blueprint $table) {
            $table->dropColumn('default_reasoning_effort');
        });
    }
};
