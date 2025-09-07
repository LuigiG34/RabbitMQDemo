<?php

namespace App\Tests\Unit;

use App\DTO\SendEmailRequest;
use App\Enum\QueuePriority;
use PHPUnit\Framework\TestCase;

final class SendEmailRequestTest extends TestCase
{
    /**
     * Check that when we build DTO from a complete array everything is mapped correctly
     * @return void
     */
    public function testFromArrayAndDefaults(): void
    {
        $dto = SendEmailRequest::fromArray([
            'recipient' => 'user@example.com',
            'subject'   => 'Hi',
            'body'      => 'Hello',
            'priority'  => 3,
        ]);

        $this->assertSame('user@example.com', $dto->recipient);
        $this->assertSame('Hi', $dto->subject);
        $this->assertSame('Hello', $dto->body);
        $this->assertSame(3, $dto->priority);
        $this->assertSame(QueuePriority::HIGH, $dto->toPriority());
    }

    /**
     * Check how the DTO behaves when the input array is empty
     * @return void
     */
    public function testFromArrayMissingFields(): void
    {
        $dto = SendEmailRequest::fromArray([]);
        $this->assertSame('', $dto->recipient);
        $this->assertSame('', $dto->subject);
        $this->assertSame('', $dto->body);
        $this->assertSame(2, $dto->priority);
        $this->assertSame(QueuePriority::NORMAL, $dto->toPriority());
    }
}
