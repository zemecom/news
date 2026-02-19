<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

final class HealthCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:health-check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check if the application is healthy (DB, Redis)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            // 1. Check Database
            DB::connection()->getPdo();

            // 2. Check Redis
            Redis::connection()->command('ping');

            $this->info('System is healthy.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Health check failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
