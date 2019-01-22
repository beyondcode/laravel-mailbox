<?php

namespace BeyondCode\Mailbox\Concerns;

trait HandlesParameters
{
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
}
