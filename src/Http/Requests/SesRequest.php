<?php

namespace BeyondCode\Mailbox\Http\Requests;

use BeyondCode\Mailbox\InboundEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;

class SesRequest extends FormRequest
{
    public function validator()
    {
        return Validator::make(json_decode($this->getContent(), true), [
            'Message' => 'required',
        ]);
    }

    public function email()
    {
        /** @var InboundEmail $modelClass */
        $modelClass = config('mailbox.model');

        return $modelClass::fromMessage(json_decode(json_decode($this->getContent(), true)['Message'],true)['content']);
    }
}
