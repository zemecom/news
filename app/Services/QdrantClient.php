<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;

final readonly class QdrantClient
{
    public function __construct(
        private ClientInterface $http,
        private string $baseUrl,
        private ?string $apiKey,
        private float $timeoutSeconds,
    ) {}

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    public function apiKey(): ?string
    {
        return $this->apiKey;
    }

    public function timeoutSeconds(): float
    {
        return $this->timeoutSeconds;
    }

    /**
     * @throws GuzzleException
     */
    public function isReady(): bool
    {
        $response = $this->http->request('GET', 'readyz', [
            'headers' => $this->headers(),
            'timeout' => $this->timeoutSeconds,
        ]);

        return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws GuzzleException
     * @throws JsonException
     */
    public function collections(): array
    {
        return $this->request('GET', 'collections');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws GuzzleException
     * @throws JsonException
     */
    public function createCollection(string $collectionName, array $payload): array
    {
        return $this->request('PUT', sprintf('collections/%s', rawurlencode($collectionName)), [
            'json' => $payload,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $points
     * @return array<string, mixed>
     *
     * @throws GuzzleException
     * @throws JsonException
     */
    public function upsertPoints(string $collectionName, array $points, bool $wait = true): array
    {
        return $this->request('PUT', sprintf('collections/%s/points', rawurlencode($collectionName)), [
            'json' => [
                'points' => $points,
            ],
            'query' => [
                'wait' => $wait ? 'true' : 'false',
            ],
        ]);
    }

    /**
     * @param  list<float|int>  $vector
     * @param  array<string, mixed>|null  $filter
     * @param  bool|array<string, mixed>  $withVectors
     * @return array<string, mixed>
     *
     * @throws GuzzleException
     * @throws JsonException
     */
    public function searchPoints(
        string $collectionName,
        array $vector,
        ?array $filter = null,
        int $limit = 10,
        bool $withPayload = true,
        bool|array $withVectors = false,
    ): array {
        $payload = [
            'vector' => $vector,
            'limit' => $limit,
            'with_payload' => $withPayload,
            'with_vector' => $withVectors,
        ];

        if ($filter !== null) {
            $payload['filter'] = $filter;
        }

        return $this->request('POST', sprintf('collections/%s/points/search', rawurlencode($collectionName)), [
            'json' => $payload,
        ]);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     *
     * @throws GuzzleException
     * @throws JsonException
     */
    public function request(string $method, string $uri, array $options = []): array
    {
        $response = $this->http->request($method, ltrim($uri, '/'), [
            'headers' => $this->headers(),
            'timeout' => $this->timeoutSeconds,
            ...$options,
        ]);

        /** @var array<string, mixed> */
        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        $headers = [
            'Accept' => 'application/json',
        ];

        if ($this->apiKey !== null && $this->apiKey !== '') {
            $headers['api-key'] = $this->apiKey;
        }

        return $headers;
    }
}
