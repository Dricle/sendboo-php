<?php

declare(strict_types=1);

namespace Sendboo\Exceptions;

use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

class SendbooException extends RuntimeException
{
    /** @var array<string,mixed> */
    public array $response = [];

    public int $status = 0;

    public static function fromGuzzle(GuzzleException $e): self
    {
        if ($e instanceof BadResponseException) {
            $status = $e->getResponse()->getStatusCode();
            $body = (string) $e->getResponse()->getBody();
            $decoded = json_decode($body, true);

            $message = is_array($decoded)
                ? ($decoded['message'] ?? $decoded['error'] ?? 'Sendboo API error.')
                : 'Sendboo API error.';

            $exception = new self("Sendboo API error ({$status}): {$message}", $status, $e);
            $exception->status = $status;
            $exception->response = is_array($decoded) ? $decoded : [];

            return $exception;
        }

        return new self('Sendboo request failed: '.$e->getMessage(), 0, $e);
    }
}
