<?php

declare(strict_types=1);

namespace BeyondCode\Mailbox\Http\Requests;

use BeyondCode\Mailbox\InboundEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;

class MailboxRequest extends FormRequest
{
    public function validator()
    {
        return Validator::make($this->all(), [
            'email' => 'required',
        ]);
    }

    public function email(): InboundEmail
    {
        /** @var InboundEmail $modelClass */
        $modelClass = config('mailbox.model');

        return $modelClass::fromMessage($this->get('email'));
    }
}