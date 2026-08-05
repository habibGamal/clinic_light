<?php

declare(strict_types=1);

namespace App\Enums;

enum SelectionType: string
{
    case Single = 'single';
    case Multi = 'multi';
}
