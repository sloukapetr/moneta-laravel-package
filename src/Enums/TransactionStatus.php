<?php

declare(strict_types=1);

namespace SloukaPetr\Moneta\Enums;

enum TransactionStatus: string
{
    case Booked = 'BOOK';
    case Pending = 'PDNG';
}
