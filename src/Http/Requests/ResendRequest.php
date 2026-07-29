<?php

namespace BeyondCode\Mailbox\Http\Requests;

use BeyondCode\Mailbox\Support\ResendWebhookSignature;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use JsonException;
use LogicException;

class ResendRequest extends FormRequest
{
    protected array $payload = [];

    protected function prepareForValidation()
    {
        $secret = config('mailbox.services.resend.webhook_secret');
        $signature = app(ResendWebhookSignature::class);

        if (! is_string($secret) || ! $signature->isValidSecret($secret)) {
            throw new LogicException('Resend webhook secret is not configured correctly.');
        }

        $signed = $signature->verify(
            $this->getContent(),
            $this->header('svix-id'),
            $this->header('svix-timestamp'),
            $this->header('svix-signature'),
            $secret
        );

        abort_unless($signed, 401, 'Invalid Resend signature or timestamp.');

        try {
            $payload = json_decode($this->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new HttpResponseException(response()->json([
                'message' => 'Invalid Resend webhook payload.',
            ], 400));
        }

        if (! is_array($payload)) {
            throw new HttpResponseException(response()->json([
                'message' => 'Invalid Resend webhook payload.',
            ], 400));
        }

        $this->payload = $payload;
    }

    public function validationData()
    {
        return $this->payload;
    }

    public function rules()
    {
        return [
            'type' => ['required', 'string'],
            'data' => ['present', 'array'],
            'data.email_id' => ['required_if:type,email.received', 'string', 'min:1'],
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Invalid Resend webhook payload.',
        ], 400));
    }

    public function eventType(): string
    {
        return $this->validated('type');
    }

    public function emailId(): ?string
    {
        return $this->validated('data.email_id');
    }
}
