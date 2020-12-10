<?php

namespace BeyondCode\Mailbox\Routing;

use BeyondCode\Mailbox\Concerns\HandlesParameters;
use BeyondCode\Mailbox\Concerns\HandlesRegularExpressions;
use BeyondCode\Mailbox\InboundEmail;
use BeyondCode\Mailbox\MailboxManager;
use Exception;
use Illuminate\Container\Container;
use Illuminate\Routing\RouteDependencyResolverTrait;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\ForwardsCalls;
use ReflectionFunction;
use ZBateson\MailMimeParser\Header\Part\AddressPart;

class Mailbox
{
    use HandlesParameters,
        HandlesRegularExpressions,
        RouteDependencyResolverTrait,
        ForwardsCalls;

    protected ?Container $container;

    protected $action;

    protected array $matches = [];

    protected array $wheres = [];

    protected array $patterns = [];

    protected int $priority = 0;

    protected bool $matchEither = false;

    public function __construct(Container $container = null)
    {
        $this->container = $container ?: new Container;
    }

    public function from(string $regex): self
    {
        $this->setPattern(Pattern::FROM, $regex);

        return $this;
    }

    public function to(string $regex): self
    {
        $this->setPattern(Pattern::TO, $regex);

        return $this;
    }

    public function cc(string $regex): self
    {
        $this->setPattern(Pattern::CC, $regex);

        return $this;
    }

    public function bcc(string $regex): self
    {
        $this->setPattern(Pattern::BCC, $regex);

        return $this;
    }

    public function subject(string $regex): self
    {
        $this->setPattern(Pattern::SUBJECT, $regex);

        return $this;
    }

    protected function setPattern(string $matchBy, string $pattern): void
    {
        $this->patterns[] = new Pattern($matchBy, $pattern);
    }

    public function matchEither(): self
    {
        $this->matchEither = true;

        return $this;
    }

    public function action($action): self
    {
        $this->action = $action;

        return $this;
    }

    public function priority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    public function run(InboundEmail $email): bool
    {
        if (!$email->isValid()) {
            throw new Exception("Mail is not valid.");
        }

        if (!$this->matchFound($email)) {
            return false;
        }

        $this->isCallable() ? $this->runCallable($email) : $this->runClass($email);

        return true;
    }

    protected function matchFound(InboundEmail $message): bool
    {
        $matchedPatterns = $this->filterPatterns($message);

        return $this->matchEither ?
            $this->isPartialMatch($matchedPatterns) : $this->isFullMatch($matchedPatterns);
    }

    protected function filterPatterns(InboundEmail $message): Collection
    {
        return collect($this->patterns)->filter(function (Pattern $pattern) use ($message) {

            $matchedValues = $this->getMatchedValues($message, $pattern->matchBy);

            return $this->valueMatchesRegex($matchedValues, $pattern->regex) !== null;
        });
    }

    protected function getMatchedValues(InboundEmail $message, string $matchBy): array
    {
        switch ($matchBy) {
            case Pattern::FROM:
                return [$message->from()];
            case Pattern::TO:
                return $this->convertMessageAddresses($message->to());
            case Pattern::CC:
                return $this->convertMessageAddresses($message->cc());
            case Pattern::BCC:
                return $this->convertMessageAddresses($message->bcc());
            case Pattern::SUBJECT:
                return [$message->subject()];
            default:
                return [];
        }
    }

    protected function convertMessageAddresses($addresses): array
    {
        return collect($addresses)->map(function (AddressPart $address) {
            return $address->getEmail();
        })->toArray();
    }

    protected function valueMatchesRegex(array $matchValues, string $regex): ?string
    {
        return collect($matchValues)->first(function (string $matchValue) use ($regex) {
            return $this->matchesRegularExpression($matchValue, $regex);
        });
    }

    protected function isPartialMatch(Collection $matchedPatterns): bool
    {
        return $matchedPatterns->isNotEmpty();
    }

    protected function isFullMatch(Collection $matchedPatterns): bool
    {
        return count($this->patterns) == $matchedPatterns->count();
    }

    protected function isCallable(): bool
    {
        return is_callable($this->action);
    }

    protected function runCallable(InboundEmail $email)
    {
        $callable = $this->action;

        $parameters = $this->resolveMethodDependencies(
            [$email] + $this->parametersWithoutNulls(), new ReflectionFunction($this->action)
        );

        return $callable(...array_values($parameters));
    }

    protected function runClass(InboundEmail $email)
    {
        $method = $this->getMailboxMethod();
        $mailbox = $this->getMailbox();

        $parameters = $this->resolveClassMethodDependencies(
            [$email] + $this->parametersWithoutNulls(), $mailbox, $method
        );

        return $mailbox->{$method}(...array_values($parameters));
    }

    protected function getMailbox(): Mailbox
    {
        $class = $this->parseMailboxCallback()[0];

        return $this->container->make(ltrim($class, '\\'));
    }

    protected function getMailboxMethod(): string
    {
        return $this->parseMailboxCallback()[1] ?? '__invoke';
    }

    protected function parseMailboxCallback(): array
    {
        return Str::parseCallback($this->action);
    }

    public function __call($method, $parameters)
    {
        return $this->forwardCallTo(
            $this->container->make(MailboxManager::class), $method, $parameters
        );
    }
}
