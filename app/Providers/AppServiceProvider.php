<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Contracts\QueuePreviewClient;
use App\Services\QdrantClient;
use App\Services\RabbitMqManagementApiClient;
use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;
use Override;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {
        /** @var array{hosts?: array<int, array<string, mixed>>} $rabbitmq */
        $rabbitmq = config('queue.connections.rabbitmq', []);
        /** @var array<string, mixed> $host */
        $host = $rabbitmq['hosts'][0] ?? [];

        $this->app->scoped(AMQPStreamConnection::class, fn () => new AMQPStreamConnection(
            host: (string) ($host['host'] ?? 'rabbitmq'),
            port: (int) ($host['port'] ?? 5672),
            user: (string) ($host['user'] ?? 'guest'),
            password: (string) ($host['password'] ?? 'guest'),
            vhost: (string) ($host['vhost'] ?? '/'),
            connection_timeout: 3.0,
            read_write_timeout: 3.0,
            heartbeat: 30,
        ));

        $this->app->singleton(QueuePreviewClient::class, RabbitMqManagementApiClient::class);

        $this->app->singleton(QdrantClient::class, function (): QdrantClient {
            $qdrantUrl = rtrim((string) config('qdrant.url', 'http://qdrant:6333'), '/');
            $qdrantApiKey = (string) config('qdrant.api_key', '');
            $qdrantTimeout = (float) config('qdrant.timeout', 3.0);

            return new QdrantClient(
                http: new Client([
                    'base_uri' => $qdrantUrl.'/',
                    'http_errors' => true,
                ]),
                baseUrl: $qdrantUrl,
                apiKey: $qdrantApiKey !== '' ? $qdrantApiKey : null,
                timeoutSeconds: $qdrantTimeout,
            );
        });

        if ($this->app->environment('local')) {
            $this->app->register(\App\Providers\TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
