<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ShiftStatus;
use Database\Factories\ExpenseFactory;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use HasFactory;

    protected $fillable = [
        'expense_category_id',
        'shift_id',
        'amount',
        'created_by',
        'notes',
    ];

    protected $appends = [
        'category_id',
        'category_name',
        'creator_name',
        'is_editable',
        'date',
    ];

    /**
     * @return BelongsTo<ExpenseCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    /**
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isEditable(?Shift $activeShift = null): bool
    {
        if (! $this->shift_id) {
            return false;
        }

        if ($activeShift !== null) {
            return $this->shift_id === $activeShift->id && $activeShift->status === ShiftStatus::Open;
        }

        $shift = $this->relationLoaded('shift') && $this->shift !== null
            ? $this->shift
            : Shift::find($this->shift_id);

        return $shift?->status === ShiftStatus::Open;
    }

    public function getCategoryIdAttribute(): ?int
    {
        return $this->expense_category_id;
    }

    public function getCategoryNameAttribute(): string
    {
        return $this->category?->name ?? 'عام';
    }

    public function getCreatorNameAttribute(): string
    {
        return $this->creator?->name ?? 'المستخدم';
    }

    public function getIsEditableAttribute(): bool
    {
        return $this->isEditable();
    }

    public function getDateAttribute(): ?string
    {
        return $this->created_at?->format('Y-m-d');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = parent::toArray();
        if (isset($array['amount'])) {
            $array['amount'] = (float) $array['amount'];
        }

        return $array;
    }

    protected static function booted(): void
    {
        self::updating(function (self $expense): bool {
            $shiftStatus = Shift::query()->where('id', $expense->shift_id)->value('status');
            $isOpen = ($shiftStatus === ShiftStatus::Open || $shiftStatus === ShiftStatus::Open->value);

            if (! $isOpen) {
                throw new DomainException('لا يمكن تعديل المصروف إلا في الوردية المفتوحة حالياً.');
            }

            return true;
        });

        self::deleting(function (self $expense): bool {
            $shiftStatus = Shift::query()->where('id', $expense->shift_id)->value('status');
            $isOpen = ($shiftStatus === ShiftStatus::Open || $shiftStatus === ShiftStatus::Open->value);

            if (! $isOpen) {
                throw new DomainException('لا يمكن حذف المصروف إلا في الوردية المفتوحة حالياً.');
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
            'amount' => 'decimal:2',
            'created_at' => 'datetime:H:i',
        ];
    }
}
