<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuickPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('fees.collect') ?? false;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'exists:students,id'],
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999'],
            'payment_method' => ['required', Rule::in(['cash', 'bank', 'mobile_banking', 'cheque', 'card'])],
            'reference_no' => ['nullable', 'string', 'max:80'],
            'paid_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
