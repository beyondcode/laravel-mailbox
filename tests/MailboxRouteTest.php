<?php

namespace BeyondCode\Mailbox\Tests;

use Zend\Mail\Message as TestMail;
use BeyondCode\Mailbox\InboundEmail;
use PHPUnit\Framework\TestCase;
use BeyondCode\Mailbox\MailboxRoute;

class MailboxRouteTest extends TestCase
{

    public function emailDataProvider()
    {
        return [
            ['hello@beyondco.de', 'hello@beyondco.de', 'wrong@beyondco.de'],
            ['hello@beyondco.de', '{name}@beyondco.de', 'wrong@beyondco.com'],
        ];
    }

    /**
     * @test
     * @dataProvider emailDataProvider
     */
    public function it_matches_from_mails($fromMail, $successfulPattern, $failingPattern)
    {
        $testMail = (new TestMail())
            ->setFrom($fromMail);

        $message = new InboundEmail(['message' => $testMail->toString()]);

        $route = new MailboxRoute(MailboxRoute::FROM, $successfulPattern, 'SomeAction@handle');
        $this->assertTrue($route->matches($message));

        $route = new MailboxRoute(MailboxRoute::FROM, $failingPattern, 'SomeAction@handle');
        $this->assertFalse($route->matches($message));
    }

    /**
     * @test
     * @dataProvider emailDataProvider
     */
    public function it_matches_to_mails($toMail, $successfulPattern, $failingPattern)
    {
        $testMail = (new TestMail())
            ->setTo($toMail);

        $message = new InboundEmail(['message' => $testMail->toString()]);

        $route = new MailboxRoute(MailboxRoute::TO, $successfulPattern, 'SomeAction@handle');
        $this->assertTrue($route->matches($message));

        $route = new MailboxRoute(MailboxRoute::TO, $failingPattern, 'SomeAction@handle');
        $this->assertFalse($route->matches($message));
    }

    /**
     * @test
     * @dataProvider emailDataProvider
     */
    public function it_matches_cc_mails($ccMail, $successfulPattern, $failingPattern)
    {
        $testMail = (new TestMail())
            ->setCc($ccMail);

        $message = new InboundEmail(['message' => $testMail->toString()]);

        $route = new MailboxRoute(MailboxRoute::CC, $successfulPattern, 'SomeAction@handle');
        $this->assertTrue($route->matches($message));

        $route = new MailboxRoute(MailboxRoute::CC, $failingPattern, 'SomeAction@handle');
        $this->assertFalse($route->matches($message));
    }

    /**
     * @test
     * @dataProvider subjectDataProvider
     */
    public function it_matches_subjects($subject, $successfulPattern, $failingPattern)
    {
        $testMail = (new TestMail())
            ->setSubject($subject);

        $message = new InboundEmail(['message' => $testMail->toString()]);

        $route = new MailboxRoute(MailboxRoute::SUBJECT, $successfulPattern, 'SomeAction@handle');
        $this->assertTrue($route->matches($message));

        $route = new MailboxRoute(MailboxRoute::SUBJECT, $failingPattern, 'SomeAction@handle');
        $this->assertFalse($route->matches($message));
    }

    /** @test */
    public function it_matches_requirements()
    {
        $testMail = (new TestMail())
            ->setFrom('abc@domain.com');

        $message = new InboundEmail(['message' => $testMail->toString()]);

        $route = new MailboxRoute(MailboxRoute::FROM, '{from}@domain.com', 'SomeAction@handle');
        $route->where('from', '[0-9]+');

        $this->assertFalse($route->matches($message));

        $route = new MailboxRoute(MailboxRoute::FROM, '{from}@domain.com', 'SomeAction@handle');
        $route->where('from', '[a-z]+');

        $this->assertTrue($route->matches($message));
    }

    public function subjectDataProvider()
    {
        return [
            ['New Laravel Packages', 'New Laravel Packages', 'Old Laravel Packages'],
            ['New Laravel Packages', '{some} laravel packages', 'Laravel Packages'],
        ];
    }

    /** @test */
    public function it_returns_parameter_names()
    {
        $route = new MailboxRoute(MailboxRoute::FROM, 'someone@domain.com', 'SomeAction@handle');

        $this->assertSame([], $route->parameterNames());

        $route = new MailboxRoute(MailboxRoute::FROM, '{name}@domain.com', 'SomeAction@handle');

        $this->assertSame([
            'name',
        ], $route->parameterNames());

        $route = new MailboxRoute(MailboxRoute::FROM, '{name}@{domain}.{tld}', 'SomeAction@handle');

        $this->assertSame([
            'name',
            'domain',
            'tld',
        ], $route->parameterNames());
    }

    /** @test */
    public function it_returns_parameter_values()
    {
        $testMail = (new TestMail())
            ->setFrom('my-email@foo.com')
            ->setSubject('ABC/DEF/GEH');

        $message = new InboundEmail(['message' => $testMail->toString()]);

        $route = new MailboxRoute(MailboxRoute::FROM, 'someone@foo.com', 'SomeAction@handle');
        $route->matches($message);

        $this->assertSame([], $route->parameters());

        $route = new MailboxRoute(MailboxRoute::FROM, '{name}@foo.com', 'SomeAction@handle');
        $route->matches($message);

        $this->assertSame([
            'name' => 'my-email',
        ], $route->parameters());

        $route = new MailboxRoute(MailboxRoute::FROM, '{name}@{domain}.{tld}', 'SomeAction@handle');
        $route->matches($message);

        $this->assertSame([
            'name' => 'my-email',
            'domain' => 'foo',
            'tld' => 'com',
        ], $route->parameters());

        $route = new MailboxRoute(MailboxRoute::SUBJECT, '{a}/{b}/{c}', 'SomeAction@handle');
        $route->matches($message);

        $this->assertSame([
            'a' => 'ABC',
            'b' => 'DEF',
            'c' => 'GEH',
        ], $route->parameters());
    }

    /** @test */
    public function it_runs_callables()
    {
        $testMail = (new TestMail())
            ->setFrom('marcel@beyondco.de');

        $message = new InboundEmail(['message' => $testMail->toString()]);

        $route = new MailboxRoute(MailboxRoute::FROM, 'marcel@beyondco.de', function ($email) use ($message) {
            $this->assertSame($email, $message);
        });

        $route->matches($message);

        $route->run($message);
    }

    /** @test */
    public function it_passes_parameters_to_callables()
    {
        $testMail = (new TestMail())
            ->setFrom('marcel@beyondco.de');

        $message = new InboundEmail(['message' => $testMail->toString()]);

        $route = new MailboxRoute(MailboxRoute::FROM, '{name}@beyondco.de', function ($email, $name) {
            $this->assertSame($name, 'marcel');
        });

        $route->matches($message);

        $route->run($message);
    }
}