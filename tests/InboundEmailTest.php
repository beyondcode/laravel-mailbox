<?php

namespace BeyondCode\Mailbox\Tests;

use BeyondCode\Mailbox\Facades\Mailbox;
use BeyondCode\Mailbox\InboundEmail;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

class InboundEmailTest extends TestCase
{

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']['mail.driver'] = 'log';
        $app['config']['mailbox.driver'] = 'log';
    }

    /** @test */
    public function it_stores_inbound_emails()
    {
        Mailbox::from('example@beyondco.de', function($email) {
        });

        Mail::to('someone@beyondco.de')->send(new TestMail);
        Mail::to('someone@beyondco.de')->send(new TestMail);

        $this->assertSame(2, InboundEmail::query()->count());
    }

}

class TestMail extends Mailable
{
    public function build()
    {
        $this->from('example@beyondco.de')
            ->subject('This is a subject')
            ->html('<html>Example email content</html>');
    }
}