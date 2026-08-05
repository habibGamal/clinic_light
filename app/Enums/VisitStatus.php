<?php

declare(strict_types=1);

namespace App\Enums;

enum VisitStatus: string
{
    case Waiting = 'waiting';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
