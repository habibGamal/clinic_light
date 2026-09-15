<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum InvoiceStatus: string implements HasColor, HasLabel
{
    case Unpaid = 'unpaid';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Refunded = 'refunded';
    case Cancelled = 'cancelled';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Unpaid => 'غير مسددة',
            self::PartiallyPaid => 'مدفوعة جزئياً',
            self::Paid => 'مسددة بالكامل',
            self::Refunded => 'مسترجعة',
            self::Cancelled => 'ملغاة',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Unpaid => 'danger',
            self::PartiallyPaid => 'warning',
            self::Paid => 'success',
            self::Refunded => 'info',
            self::Cancelled => 'gray',
        };
    }
}
