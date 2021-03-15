<?php

namespace BeyondCode\Mailbox\Tests;

use BeyondCode\Mailbox\InboundEmail;
use BeyondCode\Mailbox\Routing\Route;
use Laminas\Mail\Message as TestMail;

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

        $route = new Route(Route::FROM, $successfulPattern, 'SomeAction@handle');
        $this->assertTrue($route->matches($message));

        $route = new Route(Route::FROM, $failingPattern, 'SomeAction@handle');
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

        $route = new Route(Route::TO, $successfulPattern, 'SomeAction@handle');
        $this->assertTrue($route->matches($message));

        $route = new Route(Route::TO, $failingPattern, 'SomeAction@handle');
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

        $route = new Route(Route::CC, $successfulPattern, 'SomeAction@handle');
        $this->assertTrue($route->matches($message));

        $route = new Route(Route::CC, $failingPattern, 'SomeAction@handle');
        $this->assertFalse($route->matches($message));
    }

    /**
     * @test
     * @dataProvider emailDataProvider
     */
    public function it_matches_bcc_mails($bccMail, $successfulPattern, $failingPattern)
    {
        $testMail = (new TestMail())
            ->setBcc($bccMail);

        $message = new InboundEmail(['message' => $testMail->toString()]);

        $route = new Route(Route::BCC, $successfulPattern, 'SomeAction@handle');
        $this->assertTrue($route->matches($message));

        $route = new Route(Route::BCC, $failingPattern, 'SomeAction@handle');
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

        $route = new Route(Route::SUBJECT, $successfulPattern, 'SomeAction@handle');
        $this->assertTrue($route->matches($message));

        $route = new Route(Route::SUBJECT, $failingPattern, 'SomeAction@handle');
        $this->assertFalse($route->matches($message));
    }

    /** @test */
    public function it_matches_requirements()
    {
        $testMail = (new TestMail())
            ->setFrom('abc@domain.com');

        $message = new InboundEmail(['message' => $testMail->toString()]);

        $route = new Route(Route::FROM, '{from}@domain.com', 'SomeAction@handle');
        $route->where('from', '[0-9]+');

        $this->assertFalse($route->matches($message));

        $route = new Route(Route::FROM, '{from}@domain.com', 'SomeAction@handle');
        $route->where('from', '[A-Za-z]+');

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
        $route = new Route(Route::FROM, 'someone@domain.com', 'SomeAction@handle');

        $this->assertSame([], $route->parameterNames());

        $route = new Route(Route::FROM, '{name}@domain.com', 'SomeAction@handle');

        $this->assertSame([
            'name',
        ], $route->parameterNames());

        $route = new Route(Route::FROM, '{name}@{domain}.{tld}', 'SomeAction@handle');

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

        $route = new Route(Route::FROM, 'someone@foo.com', 'SomeAction@handle');
        $route->matches($message);

        $this->assertSame([], $route->parameters());

        $route = new Route(Route::FROM, '{name}@foo.com', 'SomeAction@handle');
        $route->matches($message);

        $this->assertSame([
            'name' => 'my-email',
        ], $route->parameters());

        $route = new Route(Route::FROM, '{name}@{domain}.{tld}', 'SomeAction@handle');
        $route->matches($message);

        $this->assertSame([
            'name' => 'my-email',
            'domain' => 'foo',
            'tld' => 'com',
        ], $route->parameters());

        $route = new Route(Route::SUBJECT, '{a}/{b}/{c}', 'SomeAction@handle');
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

        $route = new Route(Route::FROM, 'marcel@beyondco.de', function ($email) use ($message) {
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

        $route = new Route(Route::FROM, '{name}@beyondco.de', function ($email, $name) {
            $this->assertSame($name, 'marcel');
        });

        $route->matches($message);

        $route->run($message);
    }
}
