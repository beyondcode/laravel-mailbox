<?php

namespace BeyondCode\Mailbox\Tests;

use BeyondCode\Mailbox\MailboxServiceProvider;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{

    protected function getPackageProviders($app)
    {
        return [MailboxServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        include_once __DIR__.'/../database/migrations/create_mailbox_inbound_emails_table.php.stub';

        (new \CreateMailboxInboundEmailsTable())->up();
    }
}