# sendboo-php

PHP client for the [Sendboo](https://sendboo.com) API. Track events and sync subscribers from any website.

## Install

```bash
composer require sendboo/sendboo-php
```

## Authenticate

You need a Sendboo API token plus your organization (and store) UUIDs. They map to the `Authorization: Bearer`, `X-Organization-Id` and `X-Store-Id` headers the API expects.

```php
use Sendboo\Sendboo;

$sendboo = new Sendboo(
    token:          '123|sb_...',
    organizationId: 'org-uuid',
    storeId:        'store-uuid', // optional; required for events & subscribers
);
```

## Track events

Identify the actor with an `anonymous_id` (a visitor id you generate) or a `subscriber_id`. `source` defaults to `php` and `occurred_at` to now.

```php
$sendboo->track('purchase_completed', [
    'anonymous_id' => $visitorId,
    'properties'   => ['order_id' => 1234, 'total' => 79.90],
]);
```

## Sync subscribers

`sync()` upserts every row into a Sendboo email list. Pass `unsubscribeMissing: true` to also unsubscribe people who are on the list but no longer in your data (drift removal, off by default because it's destructive).

```php
// Build the list however you fetch subscribers — here from a plain PDO query.
$rows = [];

foreach ($pdo->query('SELECT email, first_name, plan FROM users WHERE subscribed = 1') as $user) {
    $rows[] = [
        'email'      => $user['email'],
        'first_name' => $user['first_name'],
        'tags'       => $user['plan'] === 'pro' ? ['pro'] : [],
    ];
}

$result = $sendboo->subscribers()->sync('email-list-uuid', $rows, unsubscribeMissing: true);
// ['upserted' => 1200, 'unsubscribed' => 7]
```

### Laravel

The package is framework-agnostic, but in a Laravel app you can build the rows with an Eloquent collection:

```php
$rows = User::where('subscribed', true)->get()
    ->map(fn (User $user) => [
        'email'      => $user->email,
        'first_name' => $user->first_name,
        'tags'       => $user->plan === 'pro' ? ['pro'] : [],
    ])
    ->all();

$sendboo->subscribers()->sync('email-list-uuid', $rows, unsubscribeMissing: true);
```

## Manage individual subscribers

```php
$subscribers = $sendboo->subscribers();

$subscribers->upsert('email-list-uuid', [
    'email'            => 'jane@example.com',
    'first_name'       => 'Jane',
    'tags'             => ['vip'],
    'extra_attributes' => ['plan' => 'pro'],
    'skip_confirmation' => true,
]);

$subscribers->unsubscribe('email-list-uuid', 'jane@example.com');

foreach ($subscribers->all('email-list-uuid') as $subscriber) {
    // ...
}
```

## Errors

Non-2xx responses throw `Sendboo\Exceptions\SendbooException` with `->status`
and the decoded `->response` body.

## Test

```bash
composer install
composer test
```
