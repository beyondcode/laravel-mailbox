<?php

namespace BeyondCode\Mailbox\Tests;

use BeyondCode\Mailbox\Facades\MailboxGroup;
use BeyondCode\Mailbox\InboundEmail;
use BeyondCode\Mailbox\Routing\Mailbox;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

class InboundEmailTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $this->catchLocalEmails();
    }

    public function processLog(MessageSent $event)
    {
        /** @var InboundEmail $modelClass */
        $modelClass = config('mailbox.model');
        $email = $modelClass::fromMessage($event->message);

        MailboxGroup::run($email);
    }

    /** @test */
    public function it_catches_logged_mails()
    {
        $mailbox = (new Mailbox())->from('{name}@beyondco.de')->action(function (InboundEmail $email, $name) {
            $this->assertSame($name, 'example');
            $this->assertSame($email->from(), 'example@beyondco.de');
            $this->assertSame($email->subject(), 'This is a subject');
        });

        MailboxGroup::add($mailbox);

        Mail::to('someone@beyondco.de')->send(new TestMail);
    }

    /** @test */
    public function it_stores_inbound_emails()
    {
        $mailbox = (new Mailbox())
            ->to('someone@beyondco.de')
            ->action(function ($email) {
            });

        MailboxGroup::add($mailbox);

        Mail::to('someone@beyondco.de')->send(new TestMail);
        Mail::to('someone-else@beyondco.de')->send(new TestMail);

        $this->assertSame(1, InboundEmail::query()->count());
    }

    /** @test */
    public function it_stores_all_inbound_emails()
    {
        config()->set('mailbox.only_store_matching_emails', false);

        $mailbox = (new Mailbox())
            ->to('someone@beyondco.de')
            ->action(function ($email) {
            });

        MailboxGroup::add($mailbox);

        Mail::to('someone@beyondco.de')->send(new TestMail);
        Mail::to('someone-else@beyondco.de')->send(new TestMail);

        $this->assertSame(2, InboundEmail::query()->count());
    }

    /** @test */
    public function it_can_use_fallbacks()
    {
        MailboxGroup::fallback(function (InboundEmail $email) {
            Mail::fake();

            $email->reply(new ReplyMail);
        });

        Mail::to('someone@beyondco.de')->send(new TestMail);

        Mail::assertSent(ReplyMail::class);
    }

    /** @test */
    public function it_stores_inbound_emails_with_fallback()
    {
        MailboxGroup::fallback(function ($email) {
        });

        Mail::to('someone@beyondco.de')->send(new TestMail);
        Mail::to('someone-else@beyondco.de')->send(new TestMail);

        $this->assertSame(2, InboundEmail::query()->count());
    }

    /** @test */
    public function it_does_not_store_inbound_emails_if_configured()
    {
        $this->app['config']['mailbox.store_incoming_emails_for_days'] = 0;

        $mailbox = (new Mailbox())
            ->from('example@beyondco.de')
            ->action(function ($email) {
            });

        MailboxGroup::add($mailbox);

        Mail::to('someone@beyondco.de')->send(new TestMail);
        Mail::to('someone@beyondco.de')->send(new TestMail);

        $this->assertSame(0, InboundEmail::query()->count());
    }

    /** @test */
    public function it_can_reply_to_mails()
    {
        $mailbox = (new Mailbox())
            ->from('example@beyondco.de')
            ->action(function (InboundEmail $email) {
                Mail::fake();

                $email->reply(new ReplyMail);
            });

        MailboxGroup::add($mailbox);

        Mail::to('someone@beyondco.de')->send(new TestMail);

        Mail::assertSent(ReplyMail::class);
    }

    /** @test */
    public function it_uses_the_configured_model()
    {
        $this->app['config']['mailbox.model'] = ExtendedInboundEmail::class;

        $mailbox = (new Mailbox())->from('example@beyondco.de')->action(function ($email) {
            $this->assertInstanceOf(ExtendedInboundEmail::class, $email);
        });

        MailboxGroup::add($mailbox);

        Mail::to('someone@beyondco.de')->send(new TestMail);
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

class ReplyMail extends Mailable
{
    public function build()
    {
        $this->from('marcel@beyondco.de')
            ->subject('This is my reply')
            ->html('Hi!');
    }
}

class ExtendedInboundEmail extends InboundEmail
{
}
