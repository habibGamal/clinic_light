<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'visit_id',
        'invoice_number',
        'status',
        'subtotal',
        'discount_total',
        'total_amount',
        'paid_amount',
        'remaining_amount',
    ];

    protected $appends = [
        'status_label',
    ];

    /**
     * @return BelongsTo<PatientVisit, $this>
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(PatientVisit::class, 'visit_id');
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function getStatusLabelAttribute(): ?string
    {
        return $this->status?->getLabel();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'subtotal' => 'float',
            'discount_total' => 'float',
            'total_amount' => 'float',
            'paid_amount' => 'float',
            'remaining_amount' => 'float',
        ];
    }
}
