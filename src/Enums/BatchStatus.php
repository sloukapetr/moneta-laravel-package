<?php

declare(strict_types=1);

namespace SloukaPetr\Moneta\Enums;

enum BatchStatus: string
{
    case AwaitingSignature = 'ACTC';
    case Signed = 'ACSP';
    case Rejected = 'RJCT';
}
