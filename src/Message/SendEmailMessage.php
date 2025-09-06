<?php

namespace App\Message;

final class SendEmailMessage
{
    public function __construct(
        private readonly string $messageId,
        private readonly string $recipient,
        private readonly string $subject,
        private readonly string $body
    ) {}

    public function getMessageId(): string
    {
        return $this->messageId;
    }

    public function getRecipient(): string
    {
        return $this->recipient;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getBody(): string
    {
        return $this->body;
    }
}
