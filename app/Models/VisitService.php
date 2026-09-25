<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DiscountType;
use App\Enums\VisitServiceStatus;
use Database\Factories\VisitServiceFactory;
use DomainException;
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
        'discount_type',
        'discount_value',
        'subtotal',
        'total',
        'status',
    ];

    protected $appends = [
        'service_name',
        'status_label',
        'has_report',
        'reports_count',
        'base_price',
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

    public function getServiceNameAttribute(): ?string
    {
        return $this->service?->name;
    }

    public function getBasePriceAttribute(): float
    {
        return (float) ($this->service?->base_price ?? 0);
    }

    public function getStatusLabelAttribute(): ?string
    {
        return $this->status?->getLabel();
    }

    public function getHasReportAttribute(): bool
    {
        return $this->relationLoaded('reports') ? $this->reports->isNotEmpty() : $this->reports()->exists();
    }

    public function getReportsCountAttribute(): int
    {
        return $this->relationLoaded('reports') ? $this->reports->count() : $this->reports()->count();
    }

    protected static function booted(): void
    {
        self::deleting(function (self $vs): bool {
            if ($vs->reports()->exists()) {
                throw new DomainException('لا يمكن حذف الفحص لوجود تقرير طبي مرتبط به.');
            }

            return true;
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => VisitServiceStatus::class,
            'discount_type' => DiscountType::class,
            'unit_price' => 'float',
            'discount_value' => 'float',
            'subtotal' => 'float',
            'total' => 'float',
        ];
    }
}
