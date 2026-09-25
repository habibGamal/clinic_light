<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VisitStatus;
use Database\Factories\PatientVisitFactory;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class PatientVisit extends Model
{
    /** @use HasFactory<PatientVisitFactory> */
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'referring_doctor_id',
        'shift_id',
        'visit_date',
        'status',
        'notes',
    ];

    protected $appends = [
        'status_label',
        'status_color',
        'raw_visit_date',
        'referring_doctor_name',
        'services',
    ];

    /**
     * @return HasOne<Invoice, $this>
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class, 'visit_id');
    }

    public function hasDuePayments(): bool
    {
        return $this->duePaymentAmount() > 0.0;
    }

    public function duePaymentAmount(): float
    {
        $invoice = app(\App\Services\InvoiceService::class)->syncInvoice($this);

        return (float) $invoice->remaining_amount;
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<ReferringDoctor, $this>
     */
    public function referringDoctor(): BelongsTo
    {
        return $this->belongsTo(ReferringDoctor::class);
    }

    /**
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * @return HasMany<VisitService, $this>
     */
    public function visitServices(): HasMany
    {
        return $this->hasMany(VisitService::class, 'visit_id');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'visit_id');
    }

    /**
     * @return HasManyThrough<Report, VisitService, $this>
     */
    public function reports(): HasManyThrough
    {
        return $this->hasManyThrough(Report::class, VisitService::class, 'visit_id');
    }

    /**
     * @return HasManyThrough<Attachment, VisitService, $this>
     */
    public function attachments(): HasManyThrough
    {
        return $this->hasManyThrough(Attachment::class, VisitService::class, 'visit_id');
    }

    public function getStatusLabelAttribute(): ?string
    {
        return $this->status?->getLabel();
    }

    public function getStatusColorAttribute(): ?string
    {
        return $this->status?->getColor();
    }

    public function getRawVisitDateAttribute(): ?string
    {
        return $this->visit_date?->toISOString();
    }

    public function getReferringDoctorNameAttribute(): string
    {
        return $this->referringDoctor?->name ?? 'مباشر';
    }

    /**
     * @return array<int, string>
     */
    public function getServicesAttribute(): array
    {
        if (! $this->relationLoaded('visitServices')) {
            return [];
        }

        return $this->visitServices->map(fn ($vs) => $vs->service?->name)->filter()->values()->all();
    }

    protected static function booted(): void
    {
        self::creating(function (self $visit): void {
            if (! $visit->shift_id) {
                $activeShift = null;
                if (auth()->check()) {
                    $activeShift = Shift::query()
                        ->where('user_id', auth()->id())
                        ->where('status', \App\Enums\ShiftStatus::Open)
                        ->latest('opened_at')
                        ->first();
                }
                $activeShift ??= Shift::query()
                    ->where('status', \App\Enums\ShiftStatus::Open)
                    ->latest('opened_at')
                    ->first();

                if ($activeShift) {
                    $visit->shift_id = $activeShift->id;
                }
            }
        });

        self::updating(function (self $visit): bool {
            $originalStatus = $visit->getOriginal('status');
            $originalStatusVal = $originalStatus instanceof VisitStatus ? $originalStatus : (is_string($originalStatus) ? VisitStatus::tryFrom($originalStatus) : null);

            if (in_array($originalStatusVal, [VisitStatus::Completed, VisitStatus::Cancelled], true)) {
                throw new DomainException('لا يمكن تعديل الزيارة بعد اكتمالها أو إلغائها.');
            }

            if (! Shift::query()->where('status', \App\Enums\ShiftStatus::Open)->exists()) {
                throw new DomainException('لا يمكن تعديل أو إلغاء الزيارة بدون وجود وردية مفتوحة.');
            }

            return true;
        });

        self::deleting(function (self $visit): bool {
            if (in_array($visit->status, [VisitStatus::Completed, VisitStatus::Cancelled], true)) {
                throw new DomainException('لا يمكن حذف الزيارة بعد اكتمالها أو إلغائها.');
            }

            if (! Shift::query()->where('status', \App\Enums\ShiftStatus::Open)->exists()) {
                throw new DomainException('لا يمكن حذف الزيارة بدون وجود وردية مفتوحة.');
            }

            return true;
        });

        self::updated(function (self $visit): void {
            if ($visit->wasChanged('status') && $visit->status === VisitStatus::Cancelled) {
                app(\App\Services\InvoiceService::class)->refundVisitPayments($visit);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => VisitStatus::class,
            'visit_date' => 'datetime:Y-m-d H:i',
        ];
    }
}
