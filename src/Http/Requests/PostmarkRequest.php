<?php

declare(strict_types=1);

namespace BeyondCode\Mailbox\Http\Requests;

use Illuminate\Support\Facades\Validator;

class PostmarkRequest extends MailboxRequest
{
    public function validator()
    {
        return Validator::make($this->all(), [
            'RawEmail' => 'required',
        ]);
    }
}
