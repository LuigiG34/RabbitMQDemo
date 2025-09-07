<?php

namespace App\Tests\Unit;

use App\Enum\QueuePriority;
use PHPUnit\Framework\TestCase;

final class QueuePriorityTest extends TestCase
{
    /**
     * We check how QueuePriority::fromMixed behaves
     * @return void
     */
    public function testFromMixed(): void
    {
        $this->assertSame(QueuePriority::LOW, QueuePriority::fromMixed(1));
        $this->assertSame(QueuePriority::NORMAL, QueuePriority::fromMixed(2));
        $this->assertSame(QueuePriority::HIGH, QueuePriority::fromMixed(3));

        $this->assertSame(QueuePriority::LOW, QueuePriority::fromMixed('1'));
        $this->assertSame(QueuePriority::NORMAL, QueuePriority::fromMixed('2'));
        $this->assertSame(QueuePriority::HIGH, QueuePriority::fromMixed('3'));

        $this->assertSame(QueuePriority::NORMAL, QueuePriority::fromMixed(null));
        $this->assertSame(QueuePriority::NORMAL, QueuePriority::fromMixed(999));
    }

    /**
     * We check how QueuePriority transport method behaves
     * @return void
     */
    public function testTransportMapping(): void
    {
        $this->assertSame('async_low',  QueuePriority::LOW->transport());
        $this->assertSame('async',      QueuePriority::NORMAL->transport());
        $this->assertSame('async_high', QueuePriority::HIGH->transport());
    }
}
