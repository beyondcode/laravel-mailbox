<?php

namespace BeyondCode\Mailbox\Tests\Console;

use Artisan;
use BeyondCode\Mailbox\InboundEmail;
use BeyondCode\Mailbox\Tests\TestCase;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CleanEmailsTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2018, 1, 1, 00, 00, 00));

        $this->app['config']->set('mailbox.store_incoming_emails_for_days', 31);
    }

    /** @test */
    public function it_can_clean_the_statistics()
    {
        Collection::times(200)->each(function (int $index) {
            InboundEmail::forceCreate([
                'message' => Str::random(),
                'created_at' => Carbon::now()->subDays($index)->startOfDay(),
            ]);
        });

        $this->assertCount(200, InboundEmail::all());

        Artisan::call('mailbox:clean');

        $this->assertCount(31, InboundEmail::all());

        $cutOffDate = Carbon::now()->subDays(31)->format('Y-m-d H:i:s');

        $this->assertCount(0, InboundEmail::where('created_at', '<', $cutOffDate)->get());
    }

    /** @test */
    public function it_errors_if_max_age_inf()
    {
        $this->app['config']->set('mailbox.store_incoming_emails_for_days', INF);

        Collection::times(200)->each(function (int $index) {
            InboundEmail::forceCreate([
                'message' => Str::random(),
                'created_at' => Carbon::now()->subDays($index)->startOfDay(),
            ]);
        });

        $this->assertCount(200, InboundEmail::all());

        $this->artisan('mailbox:clean')
             ->expectsOutput('mailbox:clean is disabled because store_incoming_emails_for_days is set to INF.')
             ->assertExitCode(1);

        $this->assertCount(200, InboundEmail::all());
    }
}
