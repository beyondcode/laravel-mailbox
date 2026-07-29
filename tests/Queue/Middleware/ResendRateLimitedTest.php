<?php

namespace BeyondCode\Mailbox\Tests\Queue\Middleware;

use BeyondCode\Mailbox\Jobs\ProcessResendEmail;
use BeyondCode\Mailbox\Queue\Middleware\ResendRateLimited;
use BeyondCode\Mailbox\Tests\TestCase;
use Illuminate\Cache\RateLimiter;
use Illuminate\Queue\Jobs\SyncJob;
use LogicException;
use PHPUnit\Framework\Attributes\Test;

class ResendRateLimitedTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->app->make(RateLimiter::class)->clear(ResendRateLimited::KEY);

        parent::tearDown();
    }

    #[Test]
    public function it_releases_a_real_job_through_its_queue_interactions()
    {
        config(['mailbox.services.resend.rate_limit' => 1]);

        $first = $this->queuedJob('msg_first');
        $second = $this->queuedJob('msg_second');

        $handled = 0;
        $next = function () use (&$handled) {
            $handled++;
        };

        $middleware = new ResendRateLimited;
        $middleware->handle($first, $next);
        $middleware->handle($second, $next);

        $this->assertSame(1, $handled);
        $this->assertFalse($first->job->isReleased());
        $this->assertTrue($second->job->isReleased());
        $this->assertGreaterThanOrEqual(1, $second->job->releaseDelay);
        $this->assertLessThanOrEqual(2, $second->job->releaseDelay);
    }

    #[Test]
    public function atomic_reservations_cannot_admit_more_than_the_configured_limit()
    {
        config(['mailbox.services.resend.rate_limit' => 1]);

        $limiter = new InterleavingResendRateLimiter($this->app['cache']->store());
        $this->app->instance(RateLimiter::class, $limiter);

        $first = $this->queuedJob('msg_first');
        $second = $this->queuedJob('msg_second');
        $handled = 0;
        $next = function () use (&$handled) {
            $handled++;
        };

        $middleware = new ResendRateLimited;
        $middleware->handle($first, $next);
        $middleware->handle($second, $next);

        $this->assertSame(2, $limiter->reservations);
        $this->assertSame(1, $handled);
        $this->assertFalse($first->job->isReleased());
        $this->assertTrue($second->job->isReleased());
    }

    #[Test]
    public function it_rejects_a_non_positive_limit()
    {
        config(['mailbox.services.resend.rate_limit' => 0]);

        $this->expectException(LogicException::class);

        (new ResendRateLimited)->handle(
            new ProcessResendEmail('email_123'),
            fn () => null
        );
    }

    private function queuedJob(string $emailId): ProcessResendEmail
    {
        $job = new ProcessResendEmail($emailId);
        $job->setJob(new RecordingResendQueueJob(
            $this->app,
            '{}',
            'test',
            'default'
        ));

        return $job;
    }
}

class RecordingResendQueueJob extends SyncJob
{
    public $releaseDelay;

    public function release($delay = 0)
    {
        $this->releaseDelay = $delay;

        parent::release($delay);
    }
}

class InterleavingResendRateLimiter extends RateLimiter
{
    public int $reservations = 0;

    public function tooManyAttempts($key, $maxAttempts)
    {
        return false;
    }

    public function hit($key, $decaySeconds = 60)
    {
        return ++$this->reservations;
    }

    public function increment($key, $decaySeconds = 60, $amount = 1)
    {
        return $this->reservations += $amount;
    }

    public function availableIn($key)
    {
        return 0;
    }
}
