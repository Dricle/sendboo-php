<?php

declare(strict_types=1);

namespace Sendboo\Resources;

use Sendboo\Sendboo;

final readonly class Subscribers
{
    public function __construct(private Sendboo $sendboo) {}

    /**
     * Create or update a subscriber on an email list.
     *
     * $subscriber keys: email (required), first_name, last_name,
     * tags (array), extra_attributes (array), skip_confirmation (bool).
     *
     * @param  array<string,mixed>  $subscriber
     * @return array<string,mixed>
     */
    public function upsert(string $listId, array $subscriber): array
    {
        return $this->sendboo->request('POST', "lists/{$listId}/contacts", $subscriber);
    }

    /**
     * @return array<string,mixed>
     */
    public function unsubscribe(string $listId, string $email): array
    {
        return $this->sendboo->request('DELETE', "lists/{$listId}/contacts", ['email' => $email]);
    }

    /**
     * Reconcile your app's subscriber list with a Sendboo email list.
     *
     * Every row in $subscribers is upserted. When $unsubscribeMissing is
     * true, subscribers currently on the list but absent from $subscribers
     * are unsubscribed (drift removal) — off by default since it's destructive.
     *
     * @param  array<int,array<string,mixed>>  $subscribers  rows with at least an "email" key
     * @return array{upserted:int,unsubscribed:int}
     */
    public function sync(
        string $listId,
        array $subscribers,
        bool $unsubscribeMissing = false,
        bool $skipConfirmation = true,
    ): array {
        $seen = [];

        foreach ($subscribers as $subscriber) {
            $subscriber['skip_confirmation'] = $subscriber['skip_confirmation'] ?? $skipConfirmation;
            $this->upsert($listId, $subscriber);
            $seen[strtolower((string) $subscriber['email'])] = true;
        }

        $unsubscribed = 0;

        if ($unsubscribeMissing) {
            foreach ($this->all($listId) as $current) {
                $email = strtolower((string) ($current['email'] ?? ''));

                if ($email !== '' && ! isset($seen[$email])) {
                    $this->unsubscribe($listId, $current['email']);
                    $unsubscribed++;
                }
            }
        }

        return ['upserted' => count($seen), 'unsubscribed' => $unsubscribed];
    }

    /**
     * Iterate every subscriber on a list, paging through the API.
     *
     * @return iterable<int,array<string,mixed>>
     */
    public function all(string $listId): iterable
    {
        $page = 1;

        do {
            $response = $this->sendboo->request(
                'GET',
                "lists/{$listId}/contacts",
                [],
                ['page' => $page],
            );

            $rows = $response['data'] ?? [];

            foreach ($rows as $row) {
                yield $row;
            }

            $lastPage = $response['meta']['last_page'] ?? $page;
            $page++;
        } while ($page <= $lastPage && $rows !== []);
    }
}
