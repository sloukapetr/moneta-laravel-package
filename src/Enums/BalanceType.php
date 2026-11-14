<?php

declare(strict_types=1);

namespace SloukaPetr\Moneta\Enums;

enum BalanceType: string
{
    case Booked = 'CLBD';
    case Available = 'CLAV';
}
