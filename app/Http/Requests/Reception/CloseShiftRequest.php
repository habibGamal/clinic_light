<?php

declare(strict_types=1);

namespace App\Http\Requests\Reception;

use Illuminate\Foundation\Http\FormRequest;

final class CloseShiftRequest extends FormRequest
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
            'closing_balance' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'closing_balance.required' => 'الرصيد الختامي مطلوب.',
            'closing_balance.numeric' => 'الرصيد الختامي يجب أن يكون رقماً.',
            'closing_balance.min' => 'الرصيد الختامي لا يمكن أن يكون سالباً.',
        ];
    }
}
