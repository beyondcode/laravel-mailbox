<?php

namespace BeyondCode\Mailbox;

use Illuminate\Container\Container;
use Illuminate\Routing\ControllerDispatcher;
use Illuminate\Routing\RouteDependencyResolverTrait;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionFunction;
use Symfony\Component\Routing\Route;
use ZBateson\MailMimeParser\Header\Part\AddressPart;
use Illuminate\Routing\Contracts\ControllerDispatcher as ControllerDispatcherContract;

class MailboxRoute
{
    use RouteDependencyResolverTrait;

    const FROM = 'from';
    const TO = 'to';
    const CC = 'cc';
    const SUBJECT = 'subject';

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

    public function parameterNames()
    {
        preg_match_all('/\{(.*?)\}/', $this->pattern, $matches);

        return array_map(function ($m) {
            return trim($m, '?');
        }, $matches[1]);
    }

    public function parameters()
    {
        return $this->matchToKeys(array_slice($this->matches, 1));
    }

    public function parametersWithoutNulls()
    {
        return array_filter($this->parameters(), function ($p) {
            return ! is_null($p);
        });
    }

    protected function matchToKeys(array $matches)
    {
        if (empty($parameterNames = $this->parameterNames())) {
            return [];
        }

        $parameters = array_intersect_key($matches, array_flip($parameterNames));

        return array_filter($parameters, function ($value) {
            return is_string($value) && strlen($value) > 0;
        });
    }

    public function matches(InboundEmail $message): bool
    {
        $subjects = $this->gatherMatchSubjectsFromMessage($message);

        return Collection::make($subjects)->first(function (string $subject) {
            return $this->matchesRegularExpression($subject);
        }) !== null;
    }

    protected function matchesRegularExpression(string $subject)
    {
        return (bool) preg_match($this->getRegularExpression(), $subject, $this->matches);
    }

    /**
     * We do not want to create the regular expression on our own,
     * so we just use Symfonys Route for this.
     *
     * @return string
     */
    protected function getRegularExpression(): string
    {
        $route = new Route($this->pattern);
        $route->setRequirements($this->wheres);

        $regex = $route->compile()->getRegex();

        $regex = preg_replace('/^#\^\/(.*)/', '#^$1', $regex);

        $regex = str_replace('>[^/]+)', '>.+)', $regex);

        $regex = str_replace('$#sD', '$#sDi', $regex);

        return $regex;
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

    public function where($name, $expression = null)
    {
        foreach ($this->parseWhere($name, $expression) as $name => $expression) {
            $this->wheres[$name] = $expression;
        }

        return $this;
    }

    protected function parseWhere($name, $expression)
    {
        return is_array($name) ? $name : [$name => $expression];
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

    public function mailboxDispatcher()
    {
        if ($this->container->bound(ControllerDispatcherContract::class)) {
            return $this->container->make(ControllerDispatcherContract::class);
        }

        return new ControllerDispatcher($this->container);
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