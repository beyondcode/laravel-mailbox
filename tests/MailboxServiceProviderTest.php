<?php

namespace BeyondCode\Mailbox\Tests;

use BeyondCode\Mailbox\MailboxServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\Test;

class MailboxServiceProviderTest extends TestCase
{
    #[Test]
    public function it_adds_resend_defaults_to_an_older_published_config()
    {
        $config = $this->registerProviderWith([
            'driver' => 'log',
            'services' => [
                'mailgun' => ['key' => 'published-mailgun-key'],
            ],
        ]);

        $this->assertSame(
            ['key' => 'published-mailgun-key'],
            $config->get('mailbox.services.mailgun')
        );

        $resend = $config->get('mailbox.services.resend');

        $this->assertIsArray($resend);
        $this->assertSame(
            ['api_key', 'webhook_secret', 'queue_connection', 'rate_limit'],
            array_keys($resend)
        );
    }

    #[Test]
    public function it_preserves_explicit_resend_values_while_filling_missing_defaults()
    {
        $config = $this->registerProviderWith([
            'driver' => 'log',
            'services' => [
                'resend' => [
                    'api_key' => 'published-api-key',
                    'queue_connection' => 'published-sync',
                    'rate_limit' => 2,
                ],
            ],
        ]);

        $this->assertSame(
            'published-api-key',
            $config->get('mailbox.services.resend.api_key')
        );
        $this->assertSame(
            'published-sync',
            $config->get('mailbox.services.resend.queue_connection')
        );
        $this->assertSame(2, $config->get('mailbox.services.resend.rate_limit'));
        $this->assertTrue($config->has('mailbox.services.resend.webhook_secret'));
    }

    private function registerProviderWith(array $mailboxConfig): Repository
    {
        $container = new Container;
        $config = new Repository(['mailbox' => $mailboxConfig]);
        $container->instance('config', $config);

        (new MailboxServiceProvider($container))->register();

        return $config;
    }
}
