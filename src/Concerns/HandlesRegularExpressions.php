<?php

namespace BeyondCode\Mailbox\Concerns;

use Symfony\Component\Routing\Route;

trait HandlesRegularExpressions
{
    protected function matchesRegularExpression(string $matchValue, string $regex)
    {
        preg_match($this->getRegularExpression($regex), $matchValue, $matches);

        $this->matches = array_merge($this->matches, $matches);

        return (bool) $matches;
    }

    /**
     * We do not want to create the regular expression on our own,
     * so we just use Symfony's Route for this.
     *
     * @param string $regex
     * @return string
     */
    protected function getRegularExpression(string $regex): string
    {
        $route = new Route($regex);

        $route->setRequirements($this->wheres);

        $regex = $route->compile()->getRegex();

        $regex = preg_replace('/^(#|{)\^\/(.*)/', '$1^$2', $regex);
        $regex = str_replace('>[^/]+)', '>.+)', $regex);
        $regex = str_replace('$#sD', '$#sDi', $regex);
        $regex = str_replace('$}sD', '$}sDi', $regex);

        return $regex;
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
}
