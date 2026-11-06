<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('finance.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'academic_session_id' => ['required', 'exists:academic_sessions,id'],
            'title' => ['required', 'string', 'max:150'],
            // The invoice total is the sum of its line items; `amount` is kept
            // for back-compat and is recalculated server-side on save.
            'amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:200'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001', 'max:999999'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'expense_date' => ['required', 'date'],
            'payment_method' => ['required', Rule::in(['cash', 'bank', 'mobile_banking', 'cheque', 'card'])],
            'reference_no' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:2000'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:4096'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Add at least one item to the expense.',
            'items.*.description.required' => 'Every item needs a description.',
            'items.*.quantity.required' => 'Every item needs a quantity.',
            'items.*.unit_price.required' => 'Every item needs a unit price.',
        ];
    }
}
