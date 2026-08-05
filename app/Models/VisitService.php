<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VisitServiceStatus;
use Database\Factories\VisitServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class VisitService extends Model
{
    /** @use HasFactory<VisitServiceFactory> */
    use HasFactory;

    protected $fillable = [
        'visit_id',
        'service_id',
        'technician_id',
        'quantity',
        'unit_price',
        'discount_value',
        'subtotal',
        'total',
        'status',
    ];

    /**
     * @return BelongsTo<PatientVisit, $this>
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(PatientVisit::class, 'visit_id');
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    /**
     * @return HasMany<VisitServiceSelectedOption, $this>
     */
    public function selectedOptions(): HasMany
    {
        return $this->hasMany(VisitServiceSelectedOption::class);
    }

    /**
     * @return HasMany<Report, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /**
     * @return HasMany<Attachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => VisitServiceStatus::class,
            'unit_price' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }
}
