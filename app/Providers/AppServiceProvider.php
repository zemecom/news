<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[\Override]
    public function register(): void
    {
        $this->app->singleton(AMQPStreamConnection::class, fn () => new AMQPStreamConnection(
            host: (string) env('RABBITMQ_HOST', 'rabbitmq'),
            port: (int) env('RABBITMQ_PORT', 5672),
            user: (string) env('RABBITMQ_USER', 'guest'),
            password: (string) env('RABBITMQ_PASSWORD', 'guest'),
            vhost: (string) env('RABBITMQ_VHOST', '/'),
            connection_timeout: 3.0,
            read_write_timeout: 3.0,
            heartbeat: 30,
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
