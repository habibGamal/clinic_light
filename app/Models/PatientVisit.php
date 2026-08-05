<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VisitStatus;
use Database\Factories\PatientVisitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

final class PatientVisit extends Model
{
    /** @use HasFactory<PatientVisitFactory> */
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'referring_doctor_id',
        'shift_id',
        'visit_number',
        'visit_date',
        'status',
        'notes',
    ];

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

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => VisitStatus::class,
            'visit_date' => 'datetime',
        ];
    }
}
