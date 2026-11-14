<?php

declare(strict_types=1);

namespace SloukaPetr\Moneta\Enums;

enum CreditDebit: string
{
    case Credit = 'CRDT';
    case Debit = 'DBIT';
}
