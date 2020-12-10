<?php

namespace BeyondCode\Mailbox\Routing;

use BeyondCode\Mailbox\InboundEmail;
use Exception;

class MailboxGroup
{
    protected array $mailboxes = [];

    protected bool $continuousMatching = false;

    protected ?Mailbox $fallback = null;

    public function add(Mailbox $mailbox): self
    {
        $this->mailboxes[] = $mailbox;

        return $this;
    }

    /**
     * @param InboundEmail $email
     * @throws Exception
     */
    public function run(InboundEmail $email): void
    {
        $matchedAny = false;
        $mailboxes = collect($this->mailboxes)->sortByDesc('priority');

        /**
         * @var $mailbox Mailbox
         */
        foreach ($mailboxes as $mailbox) {
            $matched = $mailbox->run($email);

            if (! $matched) {
                continue;
            }

            $matchedAny = true;

            if (! $this->continuousMatching) {
                break;
            }
        }

        if (! $matchedAny && $this->fallback !== null) {
            $this->fallback->run($email);
            $matchedAny = true;
        }

        if ($this->shouldStoreInboundEmails() && $this->shouldStoreAllInboundEmails($matchedAny)) {
            $this->storeEmail($email);
        }
    }

    protected function shouldStoreInboundEmails(): bool
    {
        return config('mailbox.store_incoming_emails_for_days') > 0;
    }

    protected function shouldStoreAllInboundEmails(bool $matched): bool
    {
        return $matched ? true : ! config('mailbox.only_store_matching_emails');
    }

    protected function storeEmail(InboundEmail $email)
    {
        $email->save();
    }

    public function fallback($action): self
    {
        $mailbox = app(Mailbox::class);
        $mailbox->action($action);

        $this->fallback = $mailbox;

        return $this;
    }

    public function continuousMatching(): self
    {
        $this->continuousMatching = true;

        return $this;
    }
}
