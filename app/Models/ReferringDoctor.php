<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ReferringDoctorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ReferringDoctor extends Model
{
    /** @use HasFactory<ReferringDoctorFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'specialization',
        'address',
        'notes',
    ];

    /**
     * @return HasMany<PatientVisit, $this>
     */
    public function patientVisits(): HasMany
    {
        return $this->hasMany(PatientVisit::class);
    }
}
