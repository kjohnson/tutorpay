<?php

namespace App\Http\Requests;

use App\Models\Setting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pay_period_anchor' => Setting::payPeriodAnchorIsLocked()
                ? ['prohibited']
                : ['required', 'date_format:Y-m-d'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0', 'max:10000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pay_period_anchor.prohibited' => 'The pay period start date is locked because a pay period has been marked as paid.',
        ];
    }
}
