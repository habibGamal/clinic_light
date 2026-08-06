<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case Cash = 'cash';
    case Card = 'card';
    case Wallet = 'wallet';
    case BankTransfer = 'bank_transfer';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Cash => 'نقدي (Cash)',
            self::Card => 'بطاقة (Card)',
            self::Wallet => 'محفظة إلكترونية (Wallet)',
            self::BankTransfer => 'تحويل بنكي',
        };
    }
}
