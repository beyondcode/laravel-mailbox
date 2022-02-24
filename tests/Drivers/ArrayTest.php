<?php

namespace BeyondCode\Mailbox\Tests\Drivers;

use BeyondCode\Mailbox\Facades\Mailbox;
use BeyondCode\Mailbox\InboundEmail;
use BeyondCode\Mailbox\Tests\TestCase;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

class ArrayTest extends TestCase
{
    /** @test */
    public function it_catches_in_memory_array_mails()
    {
        Mailbox::from('{name}@beyondco.de', function (InboundEmail $email, $name) {
            $this->assertSame($name, 'example');
            $this->assertSame($email->from(), 'example@beyondco.de');
            $this->assertSame($email->subject(), 'This is a subject');
        });

        Mail::to('someone@beyondco.de')->send(new TestMail);
    }

    /** @test */
    public function it_catches_logged_mails()
    {
        $app['config']['mail.driver'] = 'log';

        Mailbox::from('{name}@beyondco.de', function (InboundEmail $email, $name) {
            $this->assertSame($name, 'example');
            $this->assertSame($email->from(), 'example@beyondco.de');
            $this->assertSame($email->subject(), 'This is a subject');
        });

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
