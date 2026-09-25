<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Report extends Model
{
    /** @use HasFactory<ReportFactory> */
    use HasFactory;

    protected $fillable = [
        'visit_service_id',
        'user_id',
        'title',
        'report_text',
    ];

    protected $appends = [
        'doctor_name',
        'service_name',
    ];

    /**
     * @return BelongsTo<VisitService, $this>
     */
    public function visitService(): BelongsTo
    {
        return $this->belongsTo(VisitService::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getDoctorNameAttribute(): ?string
    {
        return $this->doctor?->name;
    }

    public function getServiceNameAttribute(): ?string
    {
        return $this->visitService?->service?->name;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime:Y-m-d H:i',
        ];
    }
}
