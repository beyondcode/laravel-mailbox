<?php

namespace BeyondCode\Mailbox\Http\Requests;

use BeyondCode\Mailbox\InboundEmail;
use Illuminate\Foundation\Http\FormRequest;

class MailCareRequest extends FormRequest
{
    public function rules()
    {
        return [
            'content_type' => 'required|in:message/rfc2822',
        ];
    }

    public function prepareForValidation()
    {
        $this->merge([
            'content_type' => $this->headers->get('Content-type'),
        ]);
    }

    public function email()
    {
        /** @var InboundEmail $modelClass */
        $modelClass = config('mailbox.model');

        return $modelClass::fromMessage($this->getContent());
    }
}
