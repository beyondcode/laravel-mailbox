<?php

namespace BeyondCode\Mailbox\Tests\Controllers;

use BeyondCode\Mailbox\Facades\Mailbox;
use BeyondCode\Mailbox\InboundEmail;
use BeyondCode\Mailbox\Jobs\ProcessMailtrapEmail;
use BeyondCode\Mailbox\Mailtrap\Client;
use BeyondCode\Mailbox\Tests\Support\MailtrapMessage;
use BeyondCode\Mailbox\Tests\Support\MailtrapTestCase;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

class MailtrapTest extends MailtrapTestCase
{
    #[Test]
    public function it_authenticates_the_exact_raw_body_without_basic_authentication()
    {
        $this->fakeMessageDownloads();
        $handled = 0;
        Mailbox::to('support@example.test', function () use (&$handled) {
            $handled++;
        });
        $raw = "{\n \"events\": [ {\"message_id\":\"m1\", \"inbox_id\":123, \"event\":\"inbound.message_received\"} ]\n}";

        $this->postRaw($raw)->assertOk()->assertSeeText('OK');

        $this->assertSame(1, $handled);
        Http::assertSentCount(2);
    }

    #[Test]
    public function it_rejects_a_signature_for_reserialized_json()
    {
        $raw = "{ \"events\": [] }\n";
        $signature = hash_hmac('sha256', '{"events":[]}', 'test-signing-secret');

        $this->postRaw($raw, $signature)->assertUnauthorized();

        Http::assertNothingSent();
    }

    #[Test]
    #[DataProvider('invalidSignatures')]
    public function it_rejects_invalid_signatures(string $signature)
    {
        $this->postRaw('{"events":[]}', $signature)->assertUnauthorized();

        Http::assertNothingSent();
    }

    public static function invalidSignatures(): array
    {
        $valid = hash_hmac('sha256', '{"events":[]}', 'test-signing-secret');

        return [
            'empty' => [''],
            'wrong secret' => [hash_hmac('sha256', '{"events":[]}', 'wrong-secret')],
            'uppercase' => [strtoupper($valid)],
            'algorithm prefix' => ['sha256='.$valid],
            'truncated' => [substr($valid, 0, 63)],
            'non hex' => [str_repeat('z', 64)],
        ];
    }

    #[Test]
    public function it_rejects_a_missing_signature_before_processing()
    {
        $this->call('POST', '/laravel-mailbox/mailtrap', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], '{"events":[]}')->assertUnauthorized();

        Http::assertNothingSent();
    }

    #[Test]
    public function it_returns_service_unavailable_when_an_old_configuration_has_no_mailtrap_service()
    {
        config(['mailbox.services' => ['mailgun' => ['key' => 'old-key']]]);

        $this->postEvents([$this->event()])->assertStatus(503);

        Http::assertNothingSent();
    }

    #[Test]
    public function it_uses_sync_and_default_cache_when_a_partial_configuration_contains_only_credentials()
    {
        config(['queue.default' => 'redis']);
        config(['mailbox.services.mailtrap' => [
            'api_token' => 'test-api-token',
            'signing_secret' => 'test-signing-secret',
        ]]);
        $this->fakeMessageDownloads();
        $handled = 0;
        Mailbox::to('support@example.test', function () use (&$handled) {
            $handled++;
        });

        $this->postEvents([$this->event()])->assertOk();
        $this->postEvents([$this->event()])->assertOk();

        $this->assertSame(1, $handled);
        Http::assertSentCount(2);
    }

    #[Test]
    #[DataProvider('missingCredentials')]
    public function it_returns_service_unavailable_for_missing_credentials(string $key, $value)
    {
        config(['mailbox.services.mailtrap.'.$key => $value]);

        $this->postEvents([$this->event()])->assertStatus(503);

        Http::assertNothingSent();
    }

    public static function missingCredentials(): array
    {
        return [
            'null API token' => ['api_token', null],
            'empty API token' => ['api_token', ''],
            'whitespace API token' => ['api_token', '   '],
            'non string API token' => ['api_token', 123],
            'null signing secret' => ['signing_secret', null],
            'empty signing secret' => ['signing_secret', ''],
            'whitespace signing secret' => ['signing_secret', '   '],
            'non string signing secret' => ['signing_secret', ['secret']],
        ];
    }

    #[Test]
    #[DataProvider('invalidEnvelopes')]
    public function it_rejects_invalid_json_and_non_list_envelopes(string $body)
    {
        $this->postRaw($body)->assertStatus(422);

        Http::assertNothingSent();
    }

    public static function invalidEnvelopes(): array
    {
        return [
            'empty body' => [''],
            'invalid JSON' => ['{"events":'],
            'null' => ['null'],
            'scalar' => ['"hello"'],
            'top level list' => ['[]'],
            'missing events' => ['{}'],
            'null events' => ['{"events":null}'],
            'scalar events' => ['{"events":"hello"}'],
            'empty object events' => ['{"events":{}}'],
            'numeric object events' => ['{"events":{"0":{"event":"inbound.message_received","inbox_id":123,"message_id":"m1"}}}'],
            'named object events' => ['{"events":{"first":{}}}'],
        ];
    }

    #[Test]
    public function it_authenticates_before_reporting_malformed_json()
    {
        $this->postRaw('{"events":', str_repeat('0', 64))->assertUnauthorized();

        Http::assertNothingSent();
    }

    #[Test]
    public function it_accepts_an_empty_event_list()
    {
        $this->postEvents([])->assertOk();

        Http::assertNothingSent();
    }

    #[Test]
    public function it_skips_unrelated_and_malformed_events_and_attempts_valid_events()
    {
        Queue::fake();
        Log::spy();
        config(['mailbox.services.mailtrap.queue_connection' => 'redis']);

        $this->postEvents([
            ['event' => 'outbound.message_delivered'],
            null,
            'invalid',
            [],
            ['event' => 'inbound.message_received', 'inbox_id' => 123],
            $this->event(123, 'valid'),
        ])->assertOk();

        Queue::assertPushed(ProcessMailtrapEmail::class, 1);
        Queue::assertPushed(ProcessMailtrapEmail::class, fn ($job) => $job->messageId === 'valid');
        Log::shouldHaveReceived('warning')->atLeast()->once();
        Http::assertNothingSent();
    }

    #[Test]
    #[DataProvider('invalidEventObjects')]
    public function it_requires_each_event_to_be_a_json_object_with_a_string_event_type(string $invalidEvent)
    {
        Queue::fake();
        Log::spy();
        config(['mailbox.services.mailtrap.queue_connection' => 'redis']);
        $valid = json_encode($this->event(), JSON_THROW_ON_ERROR);

        $this->postRaw('{"events":['.$invalidEvent.','.$valid.']}')->assertOk();

        Queue::assertPushed(ProcessMailtrapEmail::class, 1);
        Queue::assertPushed(ProcessMailtrapEmail::class, fn ($job) => $job->messageId === 'm1');
        Log::shouldHaveReceived('warning')->atLeast()->once();
        Http::assertNothingSent();
    }

    public static function invalidEventObjects(): array
    {
        return [
            'null' => ['null'],
            'boolean' => ['true'],
            'string' => ['"inbound.message_received"'],
            'empty list' => ['[]'],
            'populated list' => ['["inbound.message_received",123,"m1"]'],
            'empty object' => ['{}'],
            'null type' => ['{"event":null,"inbox_id":123,"message_id":"m1"}'],
            'array type' => ['{"event":["inbound.message_received"],"inbox_id":123,"message_id":"m1"}'],
            'object type' => ['{"event":{},"inbox_id":123,"message_id":"m1"}'],
            'integer type' => ['{"event":123,"inbox_id":123,"message_id":"m1"}'],
        ];
    }

    #[Test]
    #[DataProvider('invalidInboxIds')]
    public function it_logs_and_skips_invalid_event_inbox_ids($inboxId)
    {
        Queue::fake();
        Log::spy();

        $this->postEvents([$this->event($inboxId)])->assertOk();

        Queue::assertNothingPushed();
        Http::assertNothingSent();
        Log::shouldHaveReceived('warning')->atLeast()->once();
    }

    public static function invalidInboxIds(): array
    {
        return [
            'null' => [null],
            'zero' => [0],
            'negative' => [-1],
            'float' => [123.5],
            'whole number float' => [123.0],
            'true' => [true],
            'false' => [false],
            'empty' => [''],
            'only whitespace' => ['   '],
            'zero string' => ['0'],
            'leading zeroes' => ['000123'],
            'negative string' => ['-1'],
            'signed string' => ['+123'],
            'decimal string' => ['123.0'],
            'exponent' => ['1e2'],
            'whitespace' => [' 123'],
            'suffix' => ['123oops'],
            'array' => [[123]],
            'overflow' => [(string) PHP_INT_MAX.'0'],
        ];
    }

    #[Test]
    #[DataProvider('invalidMessageIds')]
    public function it_logs_and_skips_invalid_message_ids($messageId)
    {
        Queue::fake();
        Log::spy();

        $this->postEvents([$this->event(123, $messageId)])->assertOk();

        Queue::assertNothingPushed();
        Http::assertNothingSent();
        Log::shouldHaveReceived('warning')->atLeast()->once();
    }

    public static function invalidMessageIds(): array
    {
        return [
            'null' => [null],
            'empty' => [''],
            'whitespace' => ['   '],
            'integer' => [123],
            'boolean' => [true],
            'array' => [['m1']],
        ];
    }

    #[Test]
    #[DataProvider('validInboxIds')]
    public function it_accepts_positive_integer_and_digit_string_inbox_ids($inboxId, int $expected)
    {
        Queue::fake();
        config(['mailbox.services.mailtrap.queue_connection' => 'redis']);

        $this->postEvents([$this->event($inboxId)])->assertOk();

        Queue::assertPushed(ProcessMailtrapEmail::class, fn ($job) => $job->inboxId === $expected);
        Http::assertNothingSent();
    }

    public static function validInboxIds(): array
    {
        return [
            'integer' => [123, 123],
            'digit string' => ['123', 123],
            'largest integer' => [PHP_INT_MAX, PHP_INT_MAX],
            'largest integer string' => [(string) PHP_INT_MAX, PHP_INT_MAX],
        ];
    }

    #[Test]
    #[DataProvider('acceptAllFilters')]
    public function it_accepts_all_inboxes_when_the_filter_is_empty($filter)
    {
        Queue::fake();
        config([
            'mailbox.services.mailtrap.inbox_id' => $filter,
            'mailbox.services.mailtrap.queue_connection' => 'redis',
        ]);

        $this->postEvents([$this->event(123, 'first'), $this->event(456, 'second')])->assertOk();

        Queue::assertPushed(ProcessMailtrapEmail::class, 2);
    }

    public static function acceptAllFilters(): array
    {
        return ['null' => [null], 'empty string' => ['']];
    }

    #[Test]
    #[DataProvider('matchingFilters')]
    public function it_only_processes_the_configured_inbox($filter, $eventInbox)
    {
        Queue::fake();
        config([
            'mailbox.services.mailtrap.inbox_id' => $filter,
            'mailbox.services.mailtrap.queue_connection' => 'redis',
        ]);

        $this->postEvents([$this->event(456, 'skip'), $this->event($eventInbox, 'keep')])->assertOk();

        Queue::assertPushed(ProcessMailtrapEmail::class, 1);
        Queue::assertPushed(ProcessMailtrapEmail::class, fn ($job) => $job->inboxId === 123 && $job->messageId === 'keep');
    }

    public static function matchingFilters(): array
    {
        return [
            'integer filter' => [123, '123'],
            'string filter' => ['123', 123],
        ];
    }

    #[Test]
    #[DataProvider('invalidFilters')]
    public function it_returns_service_unavailable_for_an_invalid_configured_filter($filter)
    {
        config(['mailbox.services.mailtrap.inbox_id' => $filter]);

        $this->postEvents([$this->event()])->assertStatus(503);

        Http::assertNothingSent();
    }

    public static function invalidFilters(): array
    {
        $values = self::invalidInboxIds();
        unset($values['null'], $values['empty']);

        return $values;
    }

    #[Test]
    public function it_dispatches_background_jobs_with_the_selected_connection_and_queue_without_http()
    {
        Queue::fake();
        config([
            'mailbox.services.mailtrap.queue_connection' => 'redis',
            'mailbox.services.mailtrap.queue' => 'inbound-mail',
        ]);

        $this->postEvents([$this->event()])->assertOk();

        Queue::assertPushedOn('inbound-mail', ProcessMailtrapEmail::class, function ($job) {
            return $job->connection === 'redis' && $job->inboxId === 123 && $job->messageId === 'm1';
        });
        Http::assertNothingSent();
        $this->assertSame(0, InboundEmail::query()->count());
    }

    #[Test]
    public function it_attempts_the_rest_of_the_batch_after_an_enqueue_failure()
    {
        config(['mailbox.services.mailtrap.queue_connection' => 'redis']);
        $attempted = [];
        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')->times(3)->andReturnUsing(function ($job) use (&$attempted) {
            $attempted[] = $job->messageId;
            if ($job->messageId === 'broken') {
                throw new RuntimeException('Queue unavailable');
            }
        });
        $this->app->instance(Dispatcher::class, $dispatcher);

        $this->postEvents([
            $this->event(123, 'first'),
            $this->event(123, 'broken'),
            $this->event(123, 'last'),
        ])->assertStatus(503);

        $this->assertSame(['first', 'broken', 'last'], $attempted);
        Http::assertNothingSent();
    }

    #[Test]
    public function it_retries_a_partial_batch_without_repeating_successful_handlers()
    {
        $attempted = [];
        $fail = true;
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('fetchMessage')->andReturnUsing(function ($inboxId, $messageId) use (&$attempted, &$fail) {
            $attempted[] = $messageId;
            if ($messageId === 'broken' && $fail) {
                throw new RuntimeException('Temporary download failure');
            }

            return str_replace('invoice-42@example.test', $messageId.'@example.test', MailtrapMessage::rich());
        });
        $this->app->instance(Client::class, $client);
        $handled = [];
        Mailbox::to('support@example.test', function (InboundEmail $email) use (&$handled) {
            $handled[] = $email->id();
        });
        $events = [$this->event(123, 'first'), $this->event(123, 'broken'), $this->event(123, 'last')];

        $this->postEvents($events)->assertStatus(503);
        $this->assertSame(['first', 'broken', 'last'], $attempted);
        $this->assertSame(['first@example.test', 'last@example.test'], $handled);
        $fail = false;

        $this->postEvents($events)->assertOk();

        $this->assertSame(['first', 'broken', 'last', 'broken'], $attempted);
        $this->assertSame(['first@example.test', 'last@example.test', 'broken@example.test'], $handled);
        $this->assertSame(3, InboundEmail::query()->count());
    }

    #[Test]
    public function it_routes_and_stores_only_once_for_duplicate_events_and_deliveries()
    {
        $this->fakeMessageDownloads();
        $handled = 0;
        Mailbox::to('support@example.test', function () use (&$handled) {
            $handled++;
        });

        $this->postEvents([$this->event(), $this->event()])->assertOk();
        $this->postEvents([$this->event()])->assertOk();

        $this->assertSame(1, $handled);
        $this->assertSame(1, InboundEmail::query()->count());
        Http::assertSentCount(2);
    }

    #[Test]
    #[DataProvider('upstreamFailures')]
    public function it_returns_service_unavailable_for_authenticated_upstream_failures(string $stage, int $status)
    {
        Http::fake([
            'https://mailtrap.io/api/*' => Http::response([
                'raw_message_url' => 'https://raw.example.test/m.eml?signature=download-secret',
            ], $stage === 'api' ? $status : 200),
            'https://raw.example.test/*' => Http::response('Download failed', $status),
        ]);

        $response = $this->postEvents([$this->event()])->assertStatus(503);

        $response->assertDontSee('test-api-token')->assertDontSee('download-secret');
        Http::assertSentCount($stage === 'api' ? 1 : 2);
        $this->assertSame(0, InboundEmail::query()->count());
    }

    public static function upstreamFailures(): array
    {
        return [
            'API unauthorized' => ['api', 401],
            'API forbidden' => ['api', 403],
            'API rate limited' => ['api', 429],
            'API unavailable' => ['api', 503],
            'download forbidden' => ['download', 403],
            'download not found' => ['download', 404],
            'download unavailable' => ['download', 503],
        ];
    }
}
