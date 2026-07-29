<?php

namespace BeyondCode\Mailbox\Queue\Middleware;

use Illuminate\Cache\RateLimiter;
use LogicException;

class ResendRateLimited
{
    public const KEY = 'laravel-mailbox:resend';

    public function handle($job, $next)
    {
        $maxAttempts = (int) config('mailbox.services.resend.rate_limit', 5);

        if ($maxAttempts < 1) {
            throw new LogicException('Resend API rate limit must be a positive integer.');
        }

        /** @var RateLimiter $limiter */
        $limiter = app(RateLimiter::class);
        $reservations = $limiter->hit(static::KEY, 1);

        if ($reservations > $maxAttempts) {
            return $job->release($limiter->availableIn(static::KEY) + 1);
        }

        return $next($job);
    }
}
