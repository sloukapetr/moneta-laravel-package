<?php

namespace SloukaPetr\Moneta\Data;

use Spatie\LaravelData\Data;

class StatementData extends Data
{
    public function __construct(
        public string $accountStatementId,
        public string $dateFrom,
        public string $dateTo,
        public string $period = '',
        public int $year = 0,
        public int $month = 0,
        public int $sequenceNumber = 0,
        public array $formats = [],
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['accountStatementId'] ?? ''),
            (string) ($data['dateFrom'] ?? ''),
            (string) ($data['dateTo'] ?? ''),
            (string) ($data['period'] ?? ''),
            (int) ($data['year'] ?? 0),
            (int) ($data['month'] ?? 0),
            (int) ($data['sequenceNumber'] ?? 0),
            (array) ($data['formats'] ?? []),
        );
    }
}
