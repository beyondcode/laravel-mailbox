<?php

namespace BeyondCode\Mailbox\Http\Requests;

use BeyondCode\Mailbox\InboundEmail;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;

class PostalRequest extends FormRequest
{
    public function validator()
    {
        return Validator::make($this->all(), [
            'id'      => 'required',
            'message' => 'required',
            'base64'  => 'required',
            'size'    => 'required|integer',
        ]);
    }

    public function email()
    {
        /** @var InboundEmail $modelClass */
        $modelClass = config('mailbox.model');
        $encoded = filter_var($this->get('base64'), FILTER_VALIDATE_BOOLEAN);
        return $modelClass::fromMessage($encoded ? base64_decode($this->get('message')) : $this->get('message'));
    }

}
