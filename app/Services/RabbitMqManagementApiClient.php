<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Contracts\QueuePreviewClient;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

final class RabbitMqManagementApiClient implements QueuePreviewClient
{
    /**
     * @return list<array<string, mixed>>
     */
    public function previewQueue(string $queueName, int $limit = 10): array
    {
        try {
            $response = $this->client()->post(
                sprintf('queues/%s/%s/get', rawurlencode($this->vhost()), rawurlencode($queueName)),
                [
                    'json' => [
                        'count' => max(1, $limit),
                        'ackmode' => 'ack_requeue_true',
                        'encoding' => 'auto',
                        'truncate' => 50_000,
                    ],
                ],
            );
        } catch (GuzzleException $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        }

        $decoded = json_decode((string) $response->getBody(), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('RabbitMQ Management API returned an unexpected preview payload.');
        }

        /** @var list<array<string, mixed>> $messages */
        $messages = array_values(array_filter($decoded, 'is_array'));

        return $messages;
    }

    private function client(): Client
    {
        $host = (string) config('queue.connections.rabbitmq.hosts.0.host', 'rabbitmq');
        $user = (string) config('queue.connections.rabbitmq.hosts.0.user', 'guest');
        $password = (string) config('queue.connections.rabbitmq.hosts.0.password', 'guest');
        $port = (int) env('RABBITMQ_MANAGEMENT_PORT', 15672);

        return new Client([
            'base_uri' => sprintf('http://%s:%d/api/', $host, $port),
            'auth' => [$user, $password],
            'connect_timeout' => 3.0,
            'timeout' => 5.0,
            'http_errors' => true,
        ]);
    }

    private function vhost(): string
    {
        return (string) config('queue.connections.rabbitmq.hosts.0.vhost', '/');
    }
}
