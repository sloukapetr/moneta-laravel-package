<?php

declare(strict_types=1);

namespace SloukaPetr\Moneta\Support;

final readonly class PaymentSymbols
{
    public function __construct(
        public ?string $vs,
        public ?string $ks,
        public ?string $ss,
    ) {}

    public static function fromReference(?string $reference): self
    {
        if ($reference === null || $reference === '') {
            return new self(null, null, null);
        }

        preg_match('/\bVS:([0-9]+)\b/i', $reference, $vs);
        preg_match('/\bKS:([0-9]+)\b/i', $reference, $ks);
        preg_match('/\bSS:([0-9]+)\b/i', $reference, $ss);

        return new self($vs[1] ?? null, $ks[1] ?? null, $ss[1] ?? null);
    }
}
