<?php

namespace BeyondCode\Mailbox\Tests;

/** Builds minimal MIME strings for route tests (inbound parsing uses zbateson/mail-mime-parser). */
final class TestMimeMessage
{
    /** @var array<string, string> */
    private array $headers = [];

    public function setFrom(string $email): self
    {
        $this->headers['From'] = $email;

        return $this;
    }

    public function setTo(string $email): self
    {
        $this->headers['To'] = $email;

        return $this;
    }

    public function setCc(string $email): self
    {
        $this->headers['Cc'] = $email;

        return $this;
    }

    public function setBcc(string $email): self
    {
        $this->headers['Bcc'] = $email;

        return $this;
    }

    public function setSubject(string $subject): self
    {
        $this->headers['Subject'] = $subject;

        return $this;
    }

    public function toString(): string
    {
        $lines = [];
        foreach (['From', 'To', 'Cc', 'Bcc', 'Subject'] as $name) {
            if (isset($this->headers[$name])) {
                $lines[] = $name.': '.$this->headers[$name];
            }
        }
        $lines[] = 'MIME-Version: 1.0';
        $lines[] = 'Content-Type: text/plain; charset=UTF-8';
        $lines[] = '';
        $lines[] = '';

        return implode("\r\n", $lines);
    }
}
