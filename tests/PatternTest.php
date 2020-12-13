<?php

namespace BeyondCode\Mailbox\Tests;

use BeyondCode\Mailbox\Facades\MailboxGroup;
use BeyondCode\Mailbox\InboundEmail;
use BeyondCode\Mailbox\Routing\Mailbox;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

class PatternTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $this->catchLocalEmails();
    }

    /** @test */
    public function it_matches_from_pattern()
    {
        $mailbox = (new Mailbox())
            ->to('{pattern}@beyondco.de')
            ->where('pattern', '.*')
            ->action(function ($email) {
            });

        MailboxGroup::add($mailbox);

        Mail::to('someone@beyondco.de')->send(new PatternTestMail);
        Mail::to('someone-else@beyondco.de')->send(new PatternTestMail);

        $this->assertSame(2, InboundEmail::query()->count());
    }

    /** @test */
    public function it_rejects_wrong_pattern()
    {
        $mailbox = (new Mailbox())
            ->to('{pattern}@beyondco.de')
            ->where('pattern', '[a-z]+')
            ->action(function ($email) {
            });

        MailboxGroup::add($mailbox);

        Mail::to('123@beyondco.de')->send(new PatternTestMail);
        Mail::to('456@beyondco.de')->send(new PatternTestMail);

        $this->assertSame(0, InboundEmail::query()->count());
    }

    /** @test */
    public function it_matches_multiple_one_line_patterns()
    {
        $mailbox = (new Mailbox())
            ->to('{username}@{provider}')
            ->where('username', '[a-z]+')
            ->where('provider', 'beyondco.de')
            ->action(function ($email) {
            });

        MailboxGroup::add($mailbox);

        Mail::to('someone@beyondco.de')->send(new PatternTestMail);
        Mail::to('someone-else@gmail.com')->send(new PatternTestMail);

        $this->assertSame(1, InboundEmail::query()->count());
    }

    /** @test */
    public function it_matches_multiple_patterns()
    {
        $mailbox = (new Mailbox())
            ->from('{pattern}@beyondco.de')
            ->to('{pattern}@beyondco.de')
            ->where('pattern', '[a-z]+')
            ->action(function ($email) {
            });

        MailboxGroup::add($mailbox);

        Mail::to('someone@beyondco.de')->send(new PatternTestMail);
        Mail::to('someone-else@beyondco.de')->send(new PatternTestMail);

        $this->assertSame(1, InboundEmail::query()->count());
    }

    /** @test */
    public function it_matches_at_least_one_pattern()
    {
        $mailbox = (new Mailbox())
            ->from('{pattern}@beyondco.de')
            ->to('someone@{provider}.com')
            ->where('pattern', '[a-z]+')
            ->where('provider', 'gmail')
            ->matchEither()
            ->action(function ($email) {
            });

        MailboxGroup::add($mailbox);

        Mail::to('someone@beyondco.de')->send(new PatternTestMail);
        Mail::to('someone-else@gmail.com')->send(new PatternTestMail);

        $this->assertSame(2, InboundEmail::query()->count());
    }
}


class PatternTestMail extends Mailable
{
    public function build()
    {
        $this->from('example@beyondco.de')
            ->subject('This is a subject')
            ->html('<html>Example email content</html>');
    }
}
