<?php

namespace BeyondCode\Mailbox\Tests;

use Zend\Mail\Message as TestMail;
use BeyondCode\Mailbox\InboundEmail;
use BeyondCode\Mailbox\MailboxRoute;
use BeyondCode\Mailbox\MailboxRouteCollection;

class MailboxRouteCollectionTest extends TestCase
{
    /** @test */
    public function it_returns_all_matching_mailbox_routes()
    {
        $collection = new MailboxRouteCollection();

        $collection->add(new MailboxRoute(MailboxRoute::FROM, 'hello@beyondco.de', ''));
        $collection->add(new MailboxRoute(MailboxRoute::FROM, '{from}@beyondco.de', ''));
        $collection->add(new MailboxRoute(MailboxRoute::FROM, 'different@laravel.com', ''));

        $testMail = (new TestMail())
            ->setFrom('hello@beyondco.de');

        $message = new InboundEmail(['message' => $testMail->toString()]);

        $matching = $collection->match($message);

        $this->assertCount(2, $matching);
    }
}
