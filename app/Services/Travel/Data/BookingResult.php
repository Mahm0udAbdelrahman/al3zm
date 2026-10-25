<?php

namespace App\Services\Travel\Data;

final class BookingResult
{
    public const CONFIRMED = 'confirmed';

    public const PENDING = 'pending';

    public const FAILED = 'failed';

    public function __construct(
        public readonly string $status,
        public readonly ?string $reference = null,
        public readonly array $data = [],
        public readonly ?string $message = null,
        public readonly ?string $heldUntil = null,
    ) {}

    public static function confirmed(?string $reference, array $data = []): self
    {
        return new self(self::CONFIRMED, $reference, $data);
    }

    public static function held(?string $reference, ?string $until, array $data = []): self
    {
        return new self(self::PENDING, $reference, $data, null, $until);
    }

    public static function failed(string $message, array $data = []): self
    {
        return new self(self::FAILED, null, $data, $message);
    }

    public function succeeded(): bool
    {
        return $this->status !== self::FAILED;
    }
}
