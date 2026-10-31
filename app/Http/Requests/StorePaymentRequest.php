<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('fees.collect') ?? false;
    }

    public function rules(): array
    {
        return [
            'allocations' => ['required', 'array'],
            'allocations.*' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'payment_method' => ['required', Rule::in(['cash', 'bank', 'mobile_banking', 'cheque', 'card'])],
            'reference_no' => ['nullable', 'string', 'max:80'],
            'paid_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $total = collect($this->input('allocations', []))->sum(fn ($amount) => (float) $amount);

                if ($total <= 0) {
                    $validator->errors()->add('allocations', 'Enter an amount against at least one fee.');
                }
            },
        ];
    }
}
