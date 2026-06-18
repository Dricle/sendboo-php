<?php

declare(strict_types=1);

namespace Sendboo;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Sendboo\Exceptions\SendbooException;
use Sendboo\Resources\Events;
use Sendboo\Resources\Subscribers;

/**
 * Entry point for the Sendboo API.
 *
 *   $sendboo = new Sendboo($token, $organizationId, $storeId);
 *   $sendboo->track('purchase_completed', ['anonymous_id' => $visitorId]);
 *   $sendboo->subscribers()->sync($listId, $rows);
 */
final class Sendboo
{
    private ClientInterface $http;

    public function __construct(
        private readonly string $token,
        private readonly string $organizationId,
        private readonly ?string $storeId = null,
        string $baseUrl = 'https://sendboo.com',
        ?ClientInterface $http = null,
    ) {
        $this->http = $http ?? new Client([
            'base_uri' => rtrim($baseUrl, '/').'/api/',
            'timeout' => 15,
        ]);
    }

    public function events(): Events
    {
        return new Events($this);
    }

    public function subscribers(): Subscribers
    {
        return new Subscribers($this);
    }

    /**
     * Shortcut for events()->track().
     *
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public function track(string $name, array $payload = []): array
    {
        return $this->events()->track($name, $payload);
    }

    /**
     * Send a request to the Sendboo API and return the decoded JSON body.
     *
     * @param  array<string,mixed>  $body
     * @param  array<string,mixed>  $query
     * @return array<string,mixed>
     */
    public function request(string $method, string $path, array $body = [], array $query = []): array
    {
        $options = ['headers' => $this->headers()];

        if ($body !== []) {
            $options['json'] = $body;
        }

        if ($query !== []) {
            $options['query'] = $query;
        }

        try {
            $response = $this->http->request($method, ltrim($path, '/'), $options);
        } catch (GuzzleException $e) {
            throw SendbooException::fromGuzzle($e);
        }

        $contents = (string) $response->getBody();

        if ($contents === '') {
            return [];
        }

        return json_decode($contents, true) ?? [];
    }

    /** @return array<string,string> */
    private function headers(): array
    {
        $headers = [
            'Authorization' => 'Bearer '.$this->token,
            'Accept' => 'application/json',
            'X-Organization-Id' => $this->organizationId,
        ];

        if ($this->storeId !== null) {
            $headers['X-Store-Id'] = $this->storeId;
        }

        return $headers;
    }
}
