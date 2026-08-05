<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\VisitServiceSelectedOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class VisitServiceSelectedOption extends Model
{
    /** @use HasFactory<VisitServiceSelectedOptionFactory> */
    use HasFactory;

    protected $fillable = [
        'visit_service_id',
        'service_option_id',
        'additional_price',
    ];

    /**
     * @return BelongsTo<VisitService, $this>
     */
    public function visitService(): BelongsTo
    {
        return $this->belongsTo(VisitService::class);
    }

    /**
     * @return BelongsTo<ServiceOption, $this>
     */
    public function serviceOption(): BelongsTo
    {
        return $this->belongsTo(ServiceOption::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'additional_price' => 'decimal:2',
        ];
    }
}
