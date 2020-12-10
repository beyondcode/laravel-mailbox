<?php

namespace BeyondCode\Mailbox\Routing;

use BeyondCode\Mailbox\InboundEmail;
use Exception;
use Illuminate\Container\Container;
use Illuminate\Routing\Route;

class MailboxGroup
{
    protected array $mailboxes = [];

    protected bool $stopAfterFirstMatch = false;

    protected Mailbox $fallback;

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

        if (!$matched && $this->fallback) {
            $this->fallback->run($email);
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

    public function fallback($action): self
    {
        $mailbox = new Mailbox();
        $mailbox->action($action);

        $this->fallback = $mailbox;

        return $this;
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
