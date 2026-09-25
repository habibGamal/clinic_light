<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Gender;
use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Patient extends Model
{
    /** @use HasFactory<PatientFactory> */
    use HasFactory;

    protected $fillable = [
        'full_name',
        'phone',
        'birth_date',
        'gender',
        'address',
        'notes',
    ];

    protected $appends = [
        'age',
        'gender_label',
    ];

    /**
     * @return HasMany<PatientVisit, $this>
     */
    public function visits(): HasMany
    {
        return $this->hasMany(PatientVisit::class);
    }

    public function getAgeAttribute(): ?int
    {
        return $this->birth_date ? (int) $this->birth_date->age : null;
    }

    public function getGenderLabelAttribute(): ?string
    {
        return $this->gender?->getLabel();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'birth_date' => 'date:Y-m-d',
        ];
    }
}
