<?php

declare(strict_types=1);

namespace Sendboo\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Sendboo\Exceptions\SendbooException;
use Sendboo\Sendboo;

final class SendbooTest extends TestCase
{
    /** @var array<int,Request> */
    private array $sent = [];

    private function sendboo(array $responses): Sendboo
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(function (callable $handler) {
            return function (Request $request, array $options) use ($handler) {
                $this->sent[] = $request;

                return $handler($request, $options);
            };
        });

        $http = new Client(['base_uri' => 'https://sendboo.com/api/', 'handler' => $stack]);

        return new Sendboo('tok', 'org-uuid', 'store-uuid', 'https://sendboo.com', $http);
    }

    public function test_track_sends_auth_and_tenant_headers_with_defaults(): void
    {
        $sendboo = $this->sendboo([new Response(202, [], '{"data":{"id":1}}')]);

        $sendboo->track('purchase_completed', ['anonymous_id' => 'abc', 'properties' => ['total' => 42]]);

        $request = $this->sent[0];
        $this->assertSame('Bearer tok', $request->getHeaderLine('Authorization'));
        $this->assertSame('org-uuid', $request->getHeaderLine('X-Organization-Id'));
        $this->assertSame('store-uuid', $request->getHeaderLine('X-Store-Id'));

        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame('purchase_completed', $body['name']);
        $this->assertSame('php', $body['source']);
        $this->assertArrayHasKey('occurred_at', $body);
    }

    public function test_track_requires_an_identifier(): void
    {
        $this->expectException(SendbooException::class);

        $this->sendboo([])->track('page_viewed', []);
    }

    public function test_sync_upserts_and_prunes_drift(): void
    {
        $sendboo = $this->sendboo([
            new Response(200, [], '{"data":{}}'), // upsert keep@x.com
            new Response(200, [], '{"data":[{"email":"keep@x.com"},{"email":"gone@x.com"}],"meta":{"last_page":1}}'), // all()
            new Response(200, [], '{"data":{}}'), // unsubscribe gone@x.com
        ]);

        $result = $sendboo->subscribers()->sync(
            'list-uuid',
            [['email' => 'keep@x.com', 'first_name' => 'Kee']],
            unsubscribeMissing: true,
        );

        $this->assertSame(['upserted' => 1, 'unsubscribed' => 1], $result);

        $unsub = json_decode((string) $this->sent[2]->getBody(), true);
        $this->assertSame('gone@x.com', $unsub['email']);
        $this->assertStringContainsString('email-lists/list-uuid/unsubscribe', (string) $this->sent[2]->getUri());
    }

    public function test_api_error_is_wrapped(): void
    {
        $sendboo = $this->sendboo([new Response(422, [], '{"message":"The email field is required."}')]);

        try {
            $sendboo->subscribers()->upsert('list-uuid', ['first_name' => 'No email']);
            $this->fail('Expected SendbooException');
        } catch (SendbooException $e) {
            $this->assertSame(422, $e->status);
            $this->assertStringContainsString('email field is required', $e->getMessage());
        }
    }
}
