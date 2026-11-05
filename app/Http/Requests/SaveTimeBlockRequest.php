<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveTimeBlockRequest extends FormRequest
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
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'label' => ['nullable', 'string', 'max:255'],
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
            'end_time.after' => 'The end time must be later than the start time.',
        ];
    }

    /**
     * The validated block attributes, with times stored as HH:MM:SS.
     *
     * @return array{start_time: string, end_time: string, label: string|null}
     */
    public function blockAttributes(): array
    {
        return [
            'start_time' => $this->validated('start_time').':00',
            'end_time' => $this->validated('end_time').':00',
            'label' => $this->validated('label'),
        ];
    }
}
