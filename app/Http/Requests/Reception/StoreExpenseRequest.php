<?php

declare(strict_types=1);

namespace App\Http\Requests\Reception;

use Illuminate\Foundation\Http\FormRequest;

final class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'expense_category_id.required' => 'تصنيف المصروف مطلوب.',
            'amount.required' => 'مبلغ المصروف مطلوب.',
            'amount.min' => 'يجب أن يكون المبلغ أكبر من صفر.',
        ];
    }
}
