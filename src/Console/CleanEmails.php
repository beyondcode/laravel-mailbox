<?php

namespace BeyondCode\Mailbox\Console;

use Carbon\Carbon;
use Illuminate\Console\Command;
use BeyondCode\Mailbox\InboundEmail;

class CleanEmails extends Command
{
    protected $signature = 'mailbox:clean';

    protected $description = 'Clean up old incoming email logs.';

    public function handle()
    {
        $this->comment('Cleaning old incoming email logs...');

        $maxAgeInDays = config('mailbox.store_incoming_emails_for_days');

        $cutOffDate = Carbon::now()->subDay($maxAgeInDays)->format('Y-m-d H:i:s');

        $amountDeleted = InboundEmail::where('created_at', '<', $cutOffDate)->delete();

        $this->info("Deleted {$amountDeleted} record(s) from the Mailbox logs.");

        $this->comment('All done!');
    }
}
