<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ServiceOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ServiceOption extends Model
{
    /** @use HasFactory<ServiceOptionFactory> */
    use HasFactory;

    protected $fillable = [
        'option_group_id',
        'name',
        'additional_price',
        'is_default',
        'sort_order',
    ];

    /**
     * @return BelongsTo<ServiceOptionGroup, $this>
     */
    public function optionGroup(): BelongsTo
    {
        return $this->belongsTo(ServiceOptionGroup::class, 'option_group_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'additional_price' => 'decimal:2',
            'is_default' => 'boolean',
        ];
    }
}
