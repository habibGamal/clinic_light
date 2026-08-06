<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DiscountType: string implements HasLabel
{
    case Fixed = 'fixed';
    case Percent = 'percent';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Fixed => 'مبلغ ثابت (EGP)',
            self::Percent => 'نسبة مئوية (%)',
        };
    }
}
