<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'visit_service_id',
        'visit_service_selected_option_id',
        'type',
        'description',
        'unit_price',
        'quantity',
        'discount_amount',
        'total',
    ];

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<VisitService, $this>
     */
    public function visitService(): BelongsTo
    {
        return $this->belongsTo(VisitService::class);
    }

    /**
     * @return BelongsTo<VisitServiceSelectedOption, $this>
     */
    public function selectedOption(): BelongsTo
    {
        return $this->belongsTo(VisitServiceSelectedOption::class, 'visit_service_selected_option_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'quantity' => 'integer',
        ];
    }
}
