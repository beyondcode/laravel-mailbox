<?php

namespace BeyondCode\Mailbox\Http\Requests;

use BeyondCode\Mailbox\InboundEmail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Http\FormRequest;

class MailCareRequest extends FormRequest
{
    public function validator()
    {
        return Validator::make($this->all(), [
            'email' => 'required',
        ]);
    }

    public function email()
    {
        return InboundEmail::fromMessage($this->get('email'));
    }
}
