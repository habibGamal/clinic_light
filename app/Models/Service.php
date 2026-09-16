<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'code',
        'base_price',
        'cost',
        'is_active',
    ];

    /**
     * @return BelongsTo<ServiceCategory, $this>
     */
    // public function category(): BelongsTo
    // {
    //     return $this->belongsTo(ServiceCategory::class, 'category_id');
    // }

    /**
     * @return BelongsTo<ServiceCategory, $this>
     */
    public function serviceCategory(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    /**
     * @return HasMany<ServiceOptionGroup, $this>
     */
    public function optionGroups(): HasMany
    {
        return $this->hasMany(ServiceOptionGroup::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<VisitService, $this>
     */
    public function visitServices(): HasMany
    {
        return $this->hasMany(VisitService::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'cost' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
