<?php

namespace BeyondCode\Mailbox;

use Carbon\Carbon;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use ZBateson\MailMimeParser\Message as MimeMessage;
use ZBateson\MailMimeParser\Header\AddressHeader;
use ZBateson\MailMimeParser\Header\Part\AddressPart;

class InboundEmail extends Model
{
    /** @var MimeMessage */
    protected $mimeMessage;

    protected $fillable = [
        'message'
    ];

    public static function fromMessage($message)
    {
        return new static([
            'message' => $message
        ]);
    }

    public function id(): string
    {
        return $this->message()->getHeaderValue('Message-Id', str_random());
    }

    public function date(): Carbon
    {
        return Carbon::make($this->message()->getHeaderValue('Date'));
    }

    public function text(): ?string
    {
        return $this->message()->getTextContent();
    }

    public function html(): ?string
    {
        return $this->message()->getHtmlContent();
    }

    public function subject(): ?string
    {
        return $this->message()->getHeaderValue('Subject');
    }

    public function from(): string
    {
        $from = $this->message()->getHeader('From');

        if ($from instanceof AddressHeader) {
            return $from->getEmail();
        }

        return '';
    }

    public function fromName(): string
    {
        $from = $this->message()->getHeader('From');

        if ($from instanceof AddressHeader) {
            return $from->getPersonName();
        }

        return '';
    }

    /**
     * @return AddressPart[]
     */
    public function to(): array
    {
        return $this->convertAddressHeader($this->message()->getHeader('To'));
    }

    /**
     * @return AddressPart[]
     */
    public function cc(): array
    {
        return $this->convertAddressHeader($this->message()->getHeader('Cc'));
    }

    protected function convertAddressHeader($header): array
    {
        if ($header instanceof AddressHeader) {
            return Collection::make($header->getAddresses())->toArray();
        }

        return [];
    }

    public function attachments()
    {
        return $this->message()->getAllAttachmentParts();
    }

    public function message(): MimeMessage
    {
        $this->mimeMessage = $this->mimeMessage ?: MimeMessage::from($this->message);

        return $this->mimeMessage;
    }

    public function reply(Mailable $mailable)
    {
        return Mail::to($this->from())->send($mailable);
    }

    public function forward($recipients)
    {
        return Mail::to($recipients)->send([
            'html' => $this->html(),
            'text' => $this->text()
        ]);
    }

    public function isValid(): bool
    {
        return $this->from() !== '' && ($this->text() !== '' || $this->html() !== '');
    }
}