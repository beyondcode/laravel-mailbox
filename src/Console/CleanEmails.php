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

        /** @var InboundEmail $modelClass */
        $modelClass = config('mailbox.model');

        $models = $modelClass::where('created_at', '<', $cutOffDate)->get();

        $models->each->delete();

        $amountDeleted = $models->count();

        $this->info("Deleted {$amountDeleted} record(s) from the Mailbox logs.");

        $this->comment('All done!');
    }
}
