<?php

declare(strict_types=1);

namespace App\Http\Requests\Reception;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateVisitRequest extends FormRequest
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
            'patient_id' => ['nullable', 'exists:patients,id'],
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'patient_notes' => ['nullable', 'string'],

            'visit_date' => ['nullable', 'date'],
            'referring_doctor_id' => ['nullable', 'exists:referring_doctors,id'],
            'notes' => ['nullable', 'string'],

            'services' => ['required', 'array', 'min:1'],
            'services.*.id' => ['nullable', 'integer'],
            'services.*.service_id' => ['required', 'exists:services,id'],
            'services.*.quantity' => ['required', 'integer', 'min:1'],
            'services.*.discount_type' => ['nullable', 'string'],
            'services.*.discount_value' => ['nullable', 'numeric', 'min:0'],
            'services.*.selected_options' => ['nullable', 'array'],
            'services.*.selected_options.*' => ['exists:service_options,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'services.required' => 'يجب اختيار خدمة واحدة على الأقل للزيارة.',
            'services.min' => 'يجب اختيار خدمة واحدة على الأقل للزيارة.',
            'full_name.required' => 'اسم المريض مطلوب.',
            'phone.required' => 'رقم هاتف المريض مطلوب.',
        ];
    }
}
