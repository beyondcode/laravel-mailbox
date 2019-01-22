<?php

namespace BeyondCode\Mailbox;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Database\Eloquent\Model;
use ZBateson\MailMimeParser\Header\AddressHeader;
use ZBateson\MailMimeParser\Message as MimeMessage;
use ZBateson\MailMimeParser\Header\Part\AddressPart;
use ZBateson\MailMimeParser\Message\Part\MessagePart;

class InboundEmail extends Model
{
    protected $table = 'mailbox_inbound_emails';

    /** @var MimeMessage */
    protected $mimeMessage;

    protected $fillable = [
        'message',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->message_id = $model->id();
        });
    }

    public static function fromMessage($message)
    {
        return new static([
            'message' => $message,
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

    /**
     * @return MessagePart[]
     */
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
        return Mail::send([], [], function ($message) use ($recipients) {
            $message->to($recipients)
                ->subject($this->subject())
                ->setBody($this->body(), $this->message()->getContentType());
        });
    }

    public function body()
    {
        return $this->isHtml() ? $this->html() : $this->text();
    }

    public function isHtml()
    {
        return $this->html() !== '';
    }

    public function isText()
    {
        return $this->text() !== '';
    }

    public function isValid(): bool
    {
        return $this->from() !== '' && ($this->text() !== '' || $this->html() !== '');
    }
}
