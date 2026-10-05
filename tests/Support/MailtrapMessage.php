<?php

namespace BeyondCode\Mailbox\Tests\Support;

final class MailtrapMessage
{
    public const PDF = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n";

    public const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=';

    public static function rich(): string
    {
        // Original test data. MIME parts use CRLF, as messages downloaded from Mailtrap do.
        return implode("\r\n", [
            'From: Alice Sender <alice@example.test>',
            'To: Support <support@example.test>',
            'Cc: Colleague <colleague@example.test>',
            'Reply-To: reply@example.test',
            'Subject: Re: Invoice 42',
            'Date: Mon, 05 Oct 2026 09:30:00 +0000',
            'Message-ID: <invoice-42@example.test>',
            'In-Reply-To: <original-42@example.test>',
            'References: <root-42@example.test> <original-42@example.test>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="mailtrap-mixed"',
            '',
            '--mailtrap-mixed',
            'Content-Type: multipart/related; boundary="mailtrap-related"',
            '',
            '--mailtrap-related',
            'Content-Type: multipart/alternative; boundary="mailtrap-alternative"',
            '',
            '--mailtrap-alternative',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: quoted-printable',
            '',
            'Thanks for the invoice.',
            '',
            'On Sun, Oct 4, 2026 at 9:00 AM Support <support@example.test> wrote:',
            '> Please review invoice 42.',
            '',
            '--mailtrap-alternative',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: quoted-printable',
            '',
            '<html><body><p>Thanks for the invoice.</p><img src=3D"cid:logo@example.test"></body></html>',
            '--mailtrap-alternative--',
            '',
            '--mailtrap-related',
            'Content-Type: image/png; name="logo.png"',
            'Content-Disposition: inline; filename="logo.png"',
            'Content-ID: <logo@example.test>',
            'Content-Transfer-Encoding: base64',
            '',
            self::PNG,
            '--mailtrap-related--',
            '',
            '--mailtrap-mixed',
            'Content-Type: application/pdf; name="invoice.pdf"',
            'Content-Disposition: attachment; filename="invoice.pdf"',
            'Content-Transfer-Encoding: base64',
            '',
            base64_encode(self::PDF),
            '--mailtrap-mixed--',
            '',
        ]);
    }
}
