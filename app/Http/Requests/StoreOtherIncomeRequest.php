<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOtherIncomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('finance.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'income_date' => ['required', 'date'],
            'other_income_category_id' => ['nullable', 'integer', 'exists:other_income_categories,id'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            // Total is the sum of the line items; recalculated server-side.
            'amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:200'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001', 'max:999999'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:99999999'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Add at least one item to this income.',
            'items.*.description.required' => 'Every item needs a description.',
            'items.*.quantity.required' => 'Every item needs a quantity.',
            'items.*.unit_price.required' => 'Every item needs a unit price.',
        ];
    }
}
