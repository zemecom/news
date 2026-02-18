<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Throwable;

final readonly class HealthCheckService
{
    public function __construct(private AMQPStreamConnection $amqp) {}

    /**
     * @return array{db:string,redis:string,rabbitmq:string}
     */
    public function check(): array
    {
        $checks = [
            'db' => 'fail',
            'redis' => 'fail',
            'rabbitmq' => 'fail',
        ];

        try {
            DB::connection()->getPdo();
            $checks['db'] = 'ok';
        } catch (Throwable) {
        }

        try {
            Redis::command('ping');
            $checks['redis'] = 'ok';
        } catch (Throwable) {
        }

        try {
            $channel = $this->amqp->channel();
            $channel->close();
            $checks['rabbitmq'] = 'ok';
        } catch (Throwable) {
        }

        return $checks;
    }
}
