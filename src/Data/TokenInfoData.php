<?php

declare(strict_types=1);

namespace SloukaPetr\Moneta\Data;

use Carbon\CarbonImmutable;

final class TokenInfoData
{
    public function __construct(
        public readonly ?CarbonImmutable $expiresAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $expiration = $data['expiration'] ?? null;

        return new self(
            is_string($expiration) && $expiration !== '' ? CarbonImmutable::parse($expiration) : null,
        );
    }

    public function daysUntilExpiry(): ?int
    {
        if ($this->expiresAt === null) {
            return null;
        }

        $today = CarbonImmutable::now($this->expiresAt->getTimezone())->startOfDay();

        return (int) $today->diffInDays($this->expiresAt->startOfDay(), false);
    }
}
