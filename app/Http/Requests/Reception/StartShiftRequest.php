<?php

declare(strict_types=1);

namespace App\Http\Requests\Reception;

use Illuminate\Foundation\Http\FormRequest;

final class StartShiftRequest extends FormRequest
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
            'opening_balance' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'opening_balance.required' => 'الرصيد الافتتاحي مطلوب.',
            'opening_balance.numeric' => 'الرصيد الافتتاحي يجب أن يكون رقماً.',
            'opening_balance.min' => 'الرصيد الافتتاحي لا يمكن أن يكون سالباً.',
        ];
    }
}
