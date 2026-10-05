<?php

namespace BeyondCode\Mailbox\Tests\Controllers;

use BeyondCode\Mailbox\Jobs\ProcessMailtrapEmail;
use BeyondCode\Mailbox\Tests\Support\MailtrapTestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class MailtrapQueueTest extends MailtrapTestCase
{
    #[Test]
    #[DataProvider('supportedConnections')]
    public function it_accepts_top_level_sync_and_connections_with_only_persistent_failover_targets(array $connections)
    {
        Queue::fake();
        config([
            'mailbox.services.mailtrap.queue_connection' => 'mailtrap-delivery',
            'mailbox.services.mailtrap.queue' => 'inbound',
            'queue.connections' => $connections,
        ]);

        $this->postEvents([$this->event()])->assertOk();

        Queue::assertPushed(ProcessMailtrapEmail::class, 1);
        Queue::assertPushedOn('inbound', ProcessMailtrapEmail::class, function ($job) {
            return $job->connection === 'mailtrap-delivery' && $job->inboxId === 123 && $job->messageId === 'm1';
        });
        Http::assertNothingSent();
    }

    public static function supportedConnections(): array
    {
        $connections = [];
        foreach (['sync', 'database', 'redis', 'sqs', 'beanstalkd'] as $driver) {
            $connections[$driver] = [['mailtrap-delivery' => ['driver' => $driver]]];
        }

        $connections['persistent failover'] = [[
            'mailtrap-delivery' => ['driver' => 'failover', 'connections' => ['primary', 'backup']],
            'primary' => ['driver' => 'redis'],
            'backup' => ['driver' => 'database'],
        ]];
        $connections['nested persistent failover'] = [[
            'mailtrap-delivery' => ['driver' => 'failover', 'connections' => ['primary', 'backup']],
            'primary' => ['driver' => 'redis'],
            'backup' => ['driver' => 'failover', 'connections' => ['remote', 'local']],
            'remote' => ['driver' => 'sqs'],
            'local' => ['driver' => 'beanstalkd'],
        ]];
        $connections['shared persistent target is not a cycle'] = [[
            'mailtrap-delivery' => ['driver' => 'failover', 'connections' => ['primary', 'backup']],
            'primary' => ['driver' => 'failover', 'connections' => ['shared']],
            'backup' => ['driver' => 'failover', 'connections' => ['shared']],
            'shared' => ['driver' => 'database'],
        ]];

        return $connections;
    }

    #[Test]
    #[DataProvider('unsupportedConnections')]
    public function it_rejects_nonpersistent_unknown_and_invalid_connections_before_dispatch(array $connections)
    {
        Queue::fake();
        config([
            'mailbox.services.mailtrap.queue_connection' => 'mailtrap-delivery',
            'queue.connections' => $connections,
        ]);

        $this->postEvents([$this->event(123, 'first'), $this->event(123, 'last')])->assertStatus(503);

        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public static function unsupportedConnections(): array
    {
        $connections = [
            'unknown connection' => [[]],
            'missing driver' => [['mailtrap-delivery' => []]],
        ];
        foreach (['deferred', 'background', 'null', 'custom', '', null, 123, true] as $index => $driver) {
            $connections['unsupported driver '.$index] = [['mailtrap-delivery' => ['driver' => $driver]]];
        }

        foreach ([
            'null targets' => null,
            'empty targets' => [],
            'string targets' => 'primary',
            'boolean targets' => true,
            'nonzero first index' => [1 => 'primary'],
            'named indexes' => ['first' => 'primary'],
            'null target' => ['primary', null],
            'integer target' => ['primary', 123],
            'array target' => ['primary', ['backup']],
            'empty target name' => ['primary', ''],
            'unknown target' => ['primary', 'missing'],
        ] as $name => $targets) {
            $connections[$name] = [[
                'mailtrap-delivery' => ['driver' => 'failover', 'connections' => $targets],
                'primary' => ['driver' => 'database'],
            ]];
        }

        $connections['missing targets'] = [['mailtrap-delivery' => ['driver' => 'failover']]];
        $connections['self cycle'] = [[
            'mailtrap-delivery' => ['driver' => 'failover', 'connections' => ['mailtrap-delivery']],
        ]];
        $connections['nested cycle'] = [[
            'mailtrap-delivery' => ['driver' => 'failover', 'connections' => ['primary', 'backup']],
            'primary' => ['driver' => 'redis'],
            'backup' => ['driver' => 'failover', 'connections' => ['last']],
            'last' => ['driver' => 'failover', 'connections' => ['backup']],
        ]];

        foreach (['sync', 'deferred', 'background', 'null', 'custom'] as $driver) {
            $connections['mixed failover with '.$driver] = [[
                'mailtrap-delivery' => ['driver' => 'failover', 'connections' => ['primary', 'backup']],
                'primary' => ['driver' => 'redis'],
                'backup' => ['driver' => $driver],
            ]];
            $connections['nested failover with '.$driver] = [[
                'mailtrap-delivery' => ['driver' => 'failover', 'connections' => ['primary', 'backup']],
                'primary' => ['driver' => 'redis'],
                'backup' => ['driver' => 'failover', 'connections' => ['remote', 'last']],
                'remote' => ['driver' => 'sqs'],
                'last' => ['driver' => $driver],
            ]];
        }

        $connections['nested empty targets'] = [[
            'mailtrap-delivery' => ['driver' => 'failover', 'connections' => ['primary', 'backup']],
            'primary' => ['driver' => 'database'],
            'backup' => ['driver' => 'failover', 'connections' => []],
        ]];
        $connections['nested unknown target'] = [[
            'mailtrap-delivery' => ['driver' => 'failover', 'connections' => ['primary', 'backup']],
            'primary' => ['driver' => 'database'],
            'backup' => ['driver' => 'failover', 'connections' => ['missing']],
        ]];

        return $connections;
    }
}
