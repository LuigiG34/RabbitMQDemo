<?php

namespace App\DTO;

use App\Enum\QueuePriority;
use Symfony\Component\Validator\Constraints as Assert;

class SendEmailRequest
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $recipient;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $subject;

    #[Assert\NotBlank]
    public string $body;

    #[Assert\Positive]
    #[Assert\Choice([1, 2, 3])] // LOW=1, NORMAL=2, HIGH=3
    public int $priority = 2;

    public static function fromArray(array $data): self
    {
        $dto = new self();

        $dto->recipient = (string) ($data['recipient'] ?? '');
        $dto->subject   = (string) ($data['subject'] ?? '');
        $dto->body      = (string) ($data['body'] ?? '');
        $dto->priority  = (int) ($data['priority'] ?? 2);

        return $dto;
    }

    public function toPriority(): QueuePriority
    {
        return QueuePriority::fromMixed($this->priority);
    }
}
