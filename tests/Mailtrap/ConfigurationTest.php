<?php

namespace BeyondCode\Mailbox\Tests\Mailtrap;

use BeyondCode\Mailbox\MailboxServiceProvider;
use BeyondCode\Mailbox\Tests\TestCase;
use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Support\Env;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ConfigurationTest extends TestCase
{
    #[Test]
    #[DataProvider('publishedConfigurations')]
    public function it_adds_defaults_to_published_configuration_and_preserves_cached_configuration(array $mailbox, bool $cached)
    {
        $app = Mockery::mock(Application::class)->makePartial();
        $app->shouldReceive('configurationIsCached')->andReturn($cached);
        $app->instance('config', new Repository(['mailbox' => $mailbox]));

        (new MailboxServiceProvider($app))->register();

        $result = $app['config']->get('mailbox');
        if ($cached) {
            $this->assertSame($mailbox, $result, 'Cached configuration must remain authoritative.');

            return;
        }

        $mailtrap = $result['services']['mailtrap'];
        $this->assertSame('custom-mailbox', $result['path']);
        $this->assertSame('old-mailgun-key', $result['services']['mailgun']['key']);
        $this->assertSame('application-value', $result['services']['custom']['key']);
        $this->assertSame(0, $result['store_incoming_emails_for_days']);
        $this->assertFalse($result['only_store_matching_emails']);
        $this->assertArrayHasKey('api_token', $mailtrap);
        $this->assertArrayHasKey('signing_secret', $mailtrap);
        $this->assertArrayHasKey('inbox_id', $mailtrap);
        $this->assertArrayHasKey('cache_store', $mailtrap);
        $this->assertArrayHasKey('queue', $mailtrap);
        $this->assertSame($mailbox['services']['mailtrap']['queue_connection'] ?? 'sync', $mailtrap['queue_connection']);

        foreach ($mailbox['services']['mailtrap'] ?? [] as $key => $value) {
            $this->assertSame($value, $mailtrap[$key], 'Published Mailtrap value changed: '.$key);
        }
    }

    public static function publishedConfigurations(): array
    {
        $old = [
            'driver' => 'mailgun',
            'path' => 'custom-mailbox',
            'store_incoming_emails_for_days' => 0,
            'only_store_matching_emails' => false,
            'services' => [
                'mailgun' => ['key' => 'old-mailgun-key'],
                'custom' => ['key' => 'application-value'],
            ],
        ];
        $partial = $old;
        $partial['driver'] = 'mailtrap';
        $partial['services']['mailtrap'] = [
            'api_token' => 'published-api-token',
            'signing_secret' => 'published-signing-secret',
        ];
        $complete = $partial;
        $complete['services']['mailtrap'] += [
            'inbox_id' => '123',
            'queue_connection' => 'redis',
            'queue' => 'inbound',
            'cache_store' => 'mailtrap-cache',
        ];
        $explicitNulls = $partial;
        $explicitNulls['services']['mailtrap'] += [
            'inbox_id' => null,
            'queue' => null,
            'cache_store' => null,
        ];

        return [
            'old' => [$old, false],
            'old cached' => [$old, true],
            'partial' => [$partial, false],
            'partial cached' => [$partial, true],
            'complete' => [$complete, false],
            'complete cached' => [$complete, true],
            'explicit nulls' => [$explicitNulls, false],
            'explicit nulls cached' => [$explicitNulls, true],
        ];
    }

    #[Test]
    public function it_reads_the_mailbox_driver_from_the_environment()
    {
        $environment = Env::getRepository();
        $previous = $environment->get('MAILBOX_DRIVER');
        $environment->set('MAILBOX_DRIVER', 'mailtrap');

        try {
            $configuration = require __DIR__.'/../../config/mailbox.php';

            $this->assertSame('mailtrap', $configuration['driver']);
            $this->assertSame('sync', $configuration['services']['mailtrap']['queue_connection']);
            $this->assertNull($configuration['services']['mailtrap']['inbox_id']);
            $this->assertNull($configuration['services']['mailtrap']['queue']);
            $this->assertNull($configuration['services']['mailtrap']['cache_store']);
        } finally {
            if ($previous === null) {
                $environment->clear('MAILBOX_DRIVER');
            } else {
                $environment->set('MAILBOX_DRIVER', $previous);
            }
        }
    }
}
