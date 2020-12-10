<?php

namespace BeyondCode\Mailbox\Routing;

use BeyondCode\Mailbox\InboundEmail;
use Exception;
use Illuminate\Container\Container;
use Illuminate\Routing\Route;

class MailboxGroup
{
    protected $mailboxes = [];

    protected $stopAfterFirstMatch = false;

    protected Route $fallbackRoute;

    protected ?Container $container;

    public function __construct(Container $container = null)
    {
        $this->container = $container ?: new Container;
    }

    public function add(Mailbox $mailbox)
    {
        $this->mailboxes[] = $mailbox;
    }

    /**
     * @param InboundEmail $email
     * @throws Exception
     */
    public function callMailboxes(InboundEmail $email): void
    {
        $matched = false;
        $ordered = collect($this->mailboxes)->sortByDesc('priority');

        /**
         * @var $mailbox Mailbox
         */
        foreach ($ordered as $mailbox) {

            $matched = $mailbox->run($email);

            if ($matched && $this->stopAfterFirstMatch) {
                break;
            }
        }

        if (!$matched && $this->fallbackRoute) {
            $this->fallbackRoute->run($email);
            $matched = true;
        }

        if ($this->shouldStoreInboundEmails() && $this->shouldStoreAllInboundEmails($matched)) {
            $this->storeEmail($email);
        }
    }

    protected function shouldStoreInboundEmails(): bool
    {
        return config('mailbox.store_incoming_emails_for_days') > 0;
    }

    protected function shouldStoreAllInboundEmails(bool $matched): bool
    {
        return $matched ? true : !config('mailbox.only_store_matching_emails');
    }

    protected function storeEmail(InboundEmail $email)
    {
        $email->save();
    }

    public function fallback($action)
    {
        $this->fallbackRoute = $this->createRoute(Route::FALLBACK, '', $action);
    }

    protected function createRoute(string $matchBy, string $pattern, $action): Route
    {
        return (new Route($matchBy, $pattern, $action))
            ->setContainer($this->container);
    }

    public function stopAfterFirstMatch(bool $stop): void
    {
        $this->stopAfterFirstMatch = $stop;
    }
}
