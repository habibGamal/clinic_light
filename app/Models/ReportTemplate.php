<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ReportTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ReportTemplate extends Model
{
    /** @use HasFactory<ReportTemplateFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'service_id',
        'content',
        'is_active',
        'user_id',
    ];

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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getFormattedContent(?string $serviceName = null): string
    {
        $resolvedName = $serviceName ?? $this->service?->name ?? 'الفحص الطبي';

        return str_replace(
            ['{service_name}', '{اسم_الخدمة}', '{service}', '{الخدمة}'],
            $resolvedName,
            $this->content
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
