<?php

declare(strict_types=1);

namespace Tests\Unit\App;

use App\Services\QdrantClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

final class QdrantClientTest extends TestCase
{
    public function test_resolves_qdrant_client_from_container_with_current_configuration(): void
    {
        config()->set('qdrant.url', 'http://qdrant:6333');
        config()->set('qdrant.api_key', 'secret-key');
        config()->set('qdrant.timeout', 7.5);

        $client = $this->app->make(QdrantClient::class);

        $this->assertSame('http://qdrant:6333', $client->baseUrl());
        $this->assertSame('secret-key', $client->apiKey());
        $this->assertSame(7.5, $client->timeoutSeconds());
    }

    public function test_collections_request_uses_api_key_and_returns_decoded_payload(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'status' => 'ok',
                'result' => [
                    'collections' => [],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]));
        $stack->push(Middleware::history($history));

        $client = new QdrantClient(
            http: new Client([
                'base_uri' => 'http://qdrant:6333/',
                'handler' => $stack,
            ]),
            baseUrl: 'http://qdrant:6333',
            apiKey: 'secret-key',
            timeoutSeconds: 4.0,
        );

        $response = $client->collections();

        $this->assertSame('ok', $response['status']);
        $this->assertSame('/collections', $history[0]['request']->getUri()->getPath());
        $this->assertSame('secret-key', $history[0]['request']->getHeaderLine('api-key'));
        $this->assertSame('application/json', $history[0]['request']->getHeaderLine('Accept'));
    }

    public function test_ready_check_accepts_plain_text_response(): void
    {
        $client = new QdrantClient(
            http: new Client([
                'base_uri' => 'http://qdrant:6333/',
                'handler' => HandlerStack::create(new MockHandler([
                    new Response(200, ['Content-Type' => 'text/plain'], 'ready'),
                ])),
            ]),
            baseUrl: 'http://qdrant:6333',
            apiKey: null,
            timeoutSeconds: 4.0,
        );

        $this->assertTrue($client->isReady());
    }
}
