<?php

namespace BeyondCode\Mailbox\Tests\Controllers;

use BeyondCode\Mailbox\Tests\Support\MailtrapTestCase;
use PHPUnit\Framework\Attributes\Test;

class MailtrapCustomRouteTest extends MailtrapTestCase
{
    protected string $mailboxPath = 'webhooks/incoming';

    #[Test]
    public function it_registers_only_post_under_the_configured_prefix()
    {
        $this->postEvents([])->assertOk();
        $this->get('/webhooks/incoming/mailtrap')->assertStatus(405);
        $this->post('/laravel-mailbox/mailtrap')->assertNotFound();
    }
}
