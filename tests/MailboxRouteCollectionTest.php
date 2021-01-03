<?php

namespace BeyondCode\Mailbox\Tests;

use BeyondCode\Mailbox\InboundEmail;
use BeyondCode\Mailbox\Routing\Route;
use BeyondCode\Mailbox\Routing\RouteCollection;
use Laminas\Mail\Message as TestMail;

class MailboxRouteCollectionTest extends TestCase
{
    /** @test */
    public function it_returns_all_matching_mailbox_routes()
    {
        $collection = new RouteCollection();

        $collection->add(new Route(Route::FROM, 'hello@beyondco.de', ''));
        $collection->add(new Route(Route::FROM, '{from}@beyondco.de', ''));
        $collection->add(new Route(Route::FROM, 'different@laravel.com', ''));

        $testMail = (new TestMail())
            ->setFrom('hello@beyondco.de');

        $message = new InboundEmail(['message' => $testMail->toString()]);

        $matching = $collection->match($message);

        $this->assertCount(2, $matching);
    }
}
