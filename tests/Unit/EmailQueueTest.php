<?php

namespace App\Tests\Unit;

use App\DTO\SendEmailRequest;
use App\Message\SendEmailMessage;
use App\Service\EmailQueue;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

final class EmailQueueTest extends TestCase
{
    /**
     * Tests that EmailQueue::dispatch() correctly:
     * - Builds a SendEmailMessage from the DTO.
     * - Dispatches it on the right transport (async_low)
     * @return void
     */
    public function testDispatchBuildsMessageAndTargetsTransport(): void
    {
        // Create mock of Bus ; we just want to capture calls
        $bus = $this->createMock(MessageBusInterface::class);

        // When dispatch() is called, dont send anything
        // Capture message and stamp
        // Return fake Envelope
        $captures = [];
        $bus->method('dispatch')->willReturnCallback(function ($message, array $stamps = []) use (&$captures) {
            $captures[] = [$message, $stamps];
            return new Envelope($message, $stamps);
        });

        // Instantiate EmailQueue with the mock
        $svc = new EmailQueue($bus);

        // Create a DTO manually
        $dto = new SendEmailRequest();
        $dto->recipient = 'user@example.com';
        $dto->subject   = 'Hi';
        $dto->body      = 'Hello!';
        $dto->priority  = 1;

        // Dispatch our DTO to the bus that will be captured
        $svc->dispatch($dto);

        // Ensure one message was dispacthed
        $this->assertCount(1, $captures);
        [$message, $stamps] = $captures[0];

        // Service really built a SendEmailMessage
        $this->assertInstanceOf(SendEmailMessage::class, $message);
        // The fields match what we passed in the DTO
        $this->assertSame('user@example.com', $message->getRecipient());
        $this->assertSame('Hi', $message->getSubject());
        $this->assertSame('Hello!', $message->getBody());
        // messageId looks like a proper UUID (XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX).
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $message->getMessageId());

        // Make sure its a real TransportNamesStamp
        $stamp = array_values(array_filter($stamps, fn($s) => $s instanceof TransportNamesStamp))[0] ?? null;
        $this->assertInstanceOf(TransportNamesStamp::class, $stamp);
        // Check that the transport is indeed "async_low"
        $this->assertSame(['async_low'], $stamp->getTransportNames());
    }
}
