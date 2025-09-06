<?php

namespace App\Service;

use App\DTO\SendEmailRequest;
use App\Enum\QueuePriority;
use App\Message\SendEmailMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Uid\Uuid;

class EmailQueue
{
    public function __construct(
        private readonly MessageBusInterface $bus
    ) {}

    public function dispatch(SendEmailRequest $request): void
    {
        $message = new SendEmailMessage(
            messageId: Uuid::v7()->toRfc4122(),
            recipient: $request->recipient,
            subject:   $request->subject,
            body:      $request->body
        );

        $transport = $request->toPriority()->transport();

        $this->bus->dispatch($message, [
            new TransportNamesStamp([$transport]),
        ]);
    }
}
