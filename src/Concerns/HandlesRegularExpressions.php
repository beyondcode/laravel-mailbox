<?php

namespace BeyondCode\Mailbox\Concerns;

use Symfony\Component\Routing\Route;

trait HandlesRegularExpressions
{
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
