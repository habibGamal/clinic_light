<?php

declare(strict_types=1);

namespace App\Http\Requests\Reception;

use Illuminate\Foundation\Http\FormRequest;

final class RecordPaymentRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
