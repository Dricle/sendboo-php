<?php

declare(strict_types=1);

namespace Sendboo\Resources;

use DateTimeImmutable;
use Sendboo\Exceptions\SendbooException;
use Sendboo\Sendboo;

final readonly class Events
{
    public function __construct(private Sendboo $sendboo) {}

    /**
     * Track an event. Identify the actor with either `anonymous_id`
     * (a visitor id you generate) or `subscriber_id`.
     *
     * Defaults: source = "php", occurred_at = now.
     *
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public function track(string $name, array $payload = []): array
    {
        if (empty($payload['anonymous_id']) && empty($payload['subscriber_id'])) {
            throw new SendbooException('track() requires either "anonymous_id" or "subscriber_id".');
        }

        $body = array_merge([
            'source' => 'php',
            'occurred_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ], $payload, ['name' => $name]);

        return $this->sendboo->request('POST', 'events', $body);
    }
}
