<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SelectionType;
use Database\Factories\ServiceOptionGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ServiceOptionGroup extends Model
{
    /** @use HasFactory<ServiceOptionGroupFactory> */
    use HasFactory;

    protected $fillable = [
        'service_id',
        'name',
        'selection_type',
        'is_required',
        'sort_order',
    ];

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return HasMany<ServiceOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(ServiceOption::class, 'option_group_id')->orderBy('sort_order');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'selection_type' => SelectionType::class,
            'is_required' => 'boolean',
        ];
    }
}
