<?php

namespace App\Http\Requests;

use App\Models\Payment;
use App\Support\Timesheet;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePaymentRequest extends FormRequest
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
            'payday' => ['required', 'date_format:Y-m-d'],
            'days' => ['required', 'array', 'min:1'],
            'days.*' => ['required', 'date_format:Y-m-d', 'distinct'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'note' => ['nullable', 'string', 'max:255'],
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
            'days.required' => 'Select at least one day to include in this payment.',
        ];
    }

    /**
     * Check no payment exists on that date and every day can be paid.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $payday = $this->payday();

                if (Payment::query()->whereDate('payday', $payday->toDateString())->exists()) {
                    $validator->errors()->add('payday', 'A payment has already been made on this date.');

                    return;
                }

                $payable = Timesheet::unpaidThrough($payday)->dateStrings();

                if (array_diff($this->validated('days'), $payable) !== []) {
                    $validator->errors()->add('days', 'Some of the selected days have already been paid, have no hours, or fall after the payment date.');
                }
            },
        ];
    }

    public function payday(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $this->input('payday'));
    }
}
