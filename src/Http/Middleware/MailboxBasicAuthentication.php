<?php

namespace BeyondCode\Mailbox\Http\Middleware;

use Closure;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class MailboxBasicAuthentication
{
    public function handle($request, Closure $next)
    {
        $user = $request->getUser();
        $password = $request->getPassword();

        if (($user === config('mailbox.basic_auth.username') && $password === config('mailbox.basic_auth.password'))) {
            return $next($request);
        }

        throw new UnauthorizedHttpException('Laravel Mailbox');
    }
}
