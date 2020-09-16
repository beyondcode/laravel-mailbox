<?php

namespace BeyondCode\Mailbox\Routing;

use BeyondCode\Mailbox\Concerns\HandlesParameters;
use BeyondCode\Mailbox\Concerns\HandlesRegularExpressions;
use BeyondCode\Mailbox\InboundEmail;
use Illuminate\Container\Container;
use Illuminate\Routing\RouteDependencyResolverTrait;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionFunction;
use ZBateson\MailMimeParser\Header\Part\AddressPart;

class Route
{
    use HandlesParameters;
    use HandlesRegularExpressions;
    use RouteDependencyResolverTrait;

    const FROM = 'from';
    const TO = 'to';
    const CC = 'cc';
    const BCC = 'bcc';
    const SUBJECT = 'subject';
    const FALLBACK = 'fallback';
    const CATCH_ALL = 'catch-all';

    protected $mailbox;

    protected $subject;

    protected $pattern;

    protected $action;

    protected $container;

    protected $matches = [];

    protected $wheres = [];

    public function __construct(string $subject, string $pattern, $action)
    {
        $this->subject = $subject;
        $this->pattern = $pattern;
        $this->action = $action;
    }

    public function setContainer(Container $container)
    {
        $this->container = $container;

        return $this;
    }

    public function subject()
    {
        return $this->subject;
    }

    public function action()
    {
        return $this->action;
    }

    public function pattern()
    {
        return $this->pattern;
    }

    public function matches(InboundEmail $message): bool
    {
        $subjects = $this->gatherMatchSubjectsFromMessage($message);

        return Collection::make($subjects)->first(function (string $subject) {
            return $this->matchesRegularExpression($subject);
        }) !== null;
    }

    protected function gatherMatchSubjectsFromMessage(InboundEmail $message)
    {
        switch ($this->subject) {
            case self::FROM:
                return [$message->from()];
            break;
            case self::TO:
                return $this->convertMessageAddresses($message->to());
            break;
            case self::CC:
                return $this->convertMessageAddresses($message->cc());
            break;
            case self::BCC:
                return $this->convertMessageAddresses($message->bcc());
                break;
            case self::SUBJECT:
                return [$message->subject()];
            break;
        }
    }

    /**
     * @param $addresses AddressPart[]
     * @return array
     */
    protected function convertMessageAddresses($addresses): array
    {
        return Collection::make($addresses)
            ->map(function (AddressPart $address) {
                return $address->getEmail();
            })->toArray();
    }

    public function run(InboundEmail $email)
    {
        $this->container = $this->container ?: new Container;

        if ($this->isMailboxAction()) {
            $this->runMailbox($email);
        } else {
            $this->runCallable($email);
        }
    }

    protected function isMailboxAction()
    {
        return is_string($this->action);
    }

    protected function runMailbox(InboundEmail $email)
    {
        $method = $this->getMailboxMethod();

        $parameters = $this->resolveClassMethodDependencies(
            [$email] + $this->parametersWithoutNulls(), $this->getMailbox(), $method
        );

        return $this->getMailbox()->{$method}(...array_values($parameters));
    }

    protected function runCallable(InboundEmail $email)
    {
        $callable = $this->action;

        return $callable(...array_values($this->resolveMethodDependencies(
            [$email] + $this->parametersWithoutNulls(), new ReflectionFunction($this->action)
        )));
    }

    public function getMailbox()
    {
        if (! $this->mailbox) {
            $class = $this->parseMailboxCallback()[0];

            $this->mailbox = $this->container->make(ltrim($class, '\\'));
        }

        return $this->mailbox;
    }

    /**
     * Get the controller method used for the route.
     *
     * @return string
     */
    protected function getMailboxMethod()
    {
        return $this->parseMailboxCallback()[1] ?? '__invoke';
    }

    /**
     * Parse the controller.
     *
     * @return array
     */
    protected function parseMailboxCallback()
    {
        return Str::parseCallback($this->action);
    }
}
