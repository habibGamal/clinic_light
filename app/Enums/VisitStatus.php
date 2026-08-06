<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum VisitStatus: string implements HasColor, HasLabel
{
    case Waiting = 'waiting';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Waiting => 'في الانتظار',
            self::Completed => 'مكتملة',
            self::Cancelled => 'ملغاة',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Waiting => 'warning',
            self::Completed => 'success',
            self::Cancelled => 'danger',
        };
    }
}
