<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'visit_id',
        'invoice_id',
        'shift_id',
        'type',
        'amount',
        'payment_method',
        'paid_at',
        'notes',
    ];

    /**
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * @return BelongsTo<PatientVisit, $this>
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(PatientVisit::class, 'visit_id');
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    protected static function booted(): void
    {
        self::creating(function (self $payment): void {
            if (! $payment->shift_id) {
                $activeShift = null;
                if (auth()->check()) {
                    $activeShift = Shift::query()
                        ->where('user_id', auth()->id())
                        ->where('status', \App\Enums\ShiftStatus::Open)
                        ->latest('opened_at')
                        ->first();
                }

                $payment->shift_id = $activeShift?->id ?? $payment->visit?->shift_id;
            }

            $payment->paid_at ??= now();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }
}
