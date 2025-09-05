<?php

namespace App\Enum;

enum QueuePriority: int
{
    case LOW = 1;
    case NORMAL = 2;
    case HIGH = 3;

    public function transport(): string
    {
        return match ($this) {
            self::HIGH => 'async_high',
            self::LOW  => 'async_low',
            default    => 'async',
        };
    }

    public static function fromMixed(int|string|null $value): self
    {
        return match ((int) $value) {
            1       => self::LOW,
            3       => self::HIGH,
            default => self::NORMAL,
        };
    }
}
