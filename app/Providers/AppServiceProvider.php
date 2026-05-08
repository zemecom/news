<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Contracts\QueuePreviewClient;
use App\Services\Contracts\WorkerSupervisor;
use App\Services\HttpWorkerSupervisor;
use App\Services\NullWorkerSupervisor;
use App\Services\QdrantClient;
use App\Services\RabbitMqManagementApiClient;
use App\Services\WorkerRuntimeTelemetryService;
use GuzzleHttp\Client;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Queue;
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
        $this->app->singleton(WorkerRuntimeTelemetryService::class, fn (): WorkerRuntimeTelemetryService => new WorkerRuntimeTelemetryService(
            (string) config('workers.telemetry_store', 'redis'),
        ));
        $this->app->singleton(WorkerSupervisor::class, function (): WorkerSupervisor {
            $driver = (string) config('workers.supervisor.driver', 'null');

            if ($driver !== 'http') {
                return new NullWorkerSupervisor;
            }

            $runtimes = config('workers.runtimes', []);

            return new HttpWorkerSupervisor(
                http: new Client([
                    'base_uri' => rtrim((string) config('workers.supervisor.url', 'http://worker-control:8081'), '/').'/',
                    'connect_timeout' => 3.0,
                    'timeout' => 5.0,
                    'http_errors' => false,
                ]),
                baseUrl: (string) config('workers.supervisor.url', 'http://worker-control:8081'),
                token: (string) config('workers.supervisor.token', ''),
                configured: true,
                runtimes: is_array($runtimes) ? array_keys($runtimes) : [],
            );
        });

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
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $runtimeName = env('WORKER_RUNTIME_NAME');

        if (! is_string($runtimeName) || $runtimeName === '') {
            return;
        }

        Queue::looping(function () use ($runtimeName): void {
            app(WorkerRuntimeTelemetryService::class)->recordHeartbeat($runtimeName);
        });

        Queue::before(function (JobProcessing $event) use ($runtimeName): void {
            app(WorkerRuntimeTelemetryService::class)->recordHeartbeat($runtimeName);
            app(WorkerRuntimeTelemetryService::class)->recordProcessedJob(
                $runtimeName,
                $event->job->resolveName(),
            );
        });

        Queue::after(function (JobProcessed $event) use ($runtimeName): void {
            app(WorkerRuntimeTelemetryService::class)->recordHeartbeat($runtimeName);
            app(WorkerRuntimeTelemetryService::class)->recordProcessedJob(
                $runtimeName,
                $event->job->resolveName(),
            );
        });

        Queue::failing(function (JobFailed $event) use ($runtimeName): void {
            $exceptionMessage = trim((string) $event->exception->getMessage());

            app(WorkerRuntimeTelemetryService::class)->recordFailedJob(
                $runtimeName,
                $event->job->resolveName(),
                $exceptionMessage !== '' ? $exceptionMessage : 'Job failed without message.',
            );
        });
    }
}
