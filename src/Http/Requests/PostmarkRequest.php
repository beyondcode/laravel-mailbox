<?php

namespace BeyondCode\Mailbox\Http\Requests;

use BeyondCode\Mailbox\InboundEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;

class PostmarkRequest extends FormRequest
{
    public function validator()
    {
        return Validator::make($this->all(), [
            'RawEmail' => 'required'
        ]);
    }

    public function email()
    {
        return InboundEmail::fromMessage($this->get('RawEmail'));
    }
}
