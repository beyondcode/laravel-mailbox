<?php

declare(strict_types=1);

namespace BeyondCode\Mailbox\Concerns;

trait HandlesParameters
{
    public function parametersWithoutNulls(): array
    {
        return array_filter($this->parameters(), function ($p) {
            return ! is_null($p);
        });
    }

    public function parameters(): array
    {
        return $this->matchToKeys(array_slice($this->matches, 1));
    }

    protected function matchToKeys(array $matches): array
    {
        if (empty($parameterNames = $this->parameterNames())) {
            return [];
        }

        $parameters = array_intersect_key($matches, array_flip($parameterNames));

        return array_filter($parameters, function ($value) {
            return is_string($value) && strlen($value) > 0;
        });
    }

    public function parameterNames(): array
    {
        $matches = [];

        foreach ($this->patterns as $pattern) {
            preg_match_all('/\{(.*?)\}/', $pattern->regex, $match);

            if (count($match) > 0 && isset($match[1][0])) {
                $matches[] = $match[1][0];
            }
        }

        return array_map(function ($m) {
            return trim($m, '?');
        }, $matches);
    }
}
