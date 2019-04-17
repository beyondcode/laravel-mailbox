<?php

namespace BeyondCode\Mailbox\Tests\Console;

use Artisan;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use BeyondCode\Mailbox\InboundEmail;
use BeyondCode\Mailbox\Tests\TestCase;

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
        Collection::times(60)->each(function (int $index) {
            InboundEmail::forceCreate([
                'message' => Str::random(),
                'created_at' => Carbon::now()->subDays($index)->startOfDay(),
            ]);
        });

        $this->assertCount(60, InboundEmail::all());

        Artisan::call('mailbox:clean');

        $this->assertCount(31, InboundEmail::all());

        $cutOffDate = Carbon::now()->subDays(31)->format('Y-m-d H:i:s');

        $this->assertCount(0, InboundEmail::where('created_at', '<', $cutOffDate)->get());
    }
}
