<?php

namespace BeyondCode\Mailbox\Console;

use BeyondCode\Mailbox\InboundEmail;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CleanEmails extends Command
{
    protected $signature = 'mailbox:clean';

    protected $description = 'Clean up old incoming email logs.';

    protected $amountDeleted = 0;

    public function handle()
    {
        $this->comment('Cleaning old incoming email logs...');

        $maxAgeInDays = config('mailbox.store_incoming_emails_for_days');

        if ($maxAgeInDays === INF) {
            $this->error($this->signature.' is disabled because store_incoming_emails_for_days is set to INF.');

            return 1;
        }

        $cutOffDate = Carbon::now()->subDay($maxAgeInDays)->format('Y-m-d H:i:s');

        /** @var InboundEmail $modelClass */
        $modelClass = config('mailbox.model');

        // chunk the deletion to avoid memory issues

        $this->amountDeleted = 0;

        $modelClass::where('created_at', '<', $cutOffDate)
            ->select('id')
            ->eachById(count: 100, callback: function ($model) {
                $model->delete();
                $this->amountDeleted++;
            });

        $this->info("Deleted {$this->amountDeleted} record(s) from the Mailbox logs.");

        $this->comment('All done!');
    }
}
