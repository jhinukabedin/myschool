<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFeeStructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('fees.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'academic_session_id' => ['required', 'exists:academic_sessions,id'],
            'class_id' => ['required', 'exists:classes,id'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.fee_category_id' => ['required', 'distinct', 'exists:fee_categories,id'],
            'items.*.amount' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.scholarship_type' => ['nullable', Rule::in(['percent', 'fixed'])],
            'items.*.scholarship_value' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'items.*.frequency' => ['required', Rule::in(['monthly', 'one_time', 'annual', 'exam'])],
            'items.*.due_day' => ['required', 'integer', 'min:1', 'max:28'],
            'items.*.is_optional' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.fee_category_id.distinct' => 'Each fee category may only appear once in a structure.',
        ];
    }

    /**
     * Wildcard rules cannot compare one indexed item against another, so
     * enforce the per-item discount ceiling explicitly.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ($this->input('items', []) as $index => $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $amount = $item['amount'] ?? null;
                    $discount = $item['discount_amount'] ?? null;

                    if (is_numeric($amount) && is_numeric($discount) && (float) $discount > (float) $amount) {
                        $validator->errors()->add(
                            "items.{$index}.discount_amount",
                            'The discount cannot exceed the fee amount.'
                        );
                    }

                    $scholarship = $item['scholarship_value'] ?? null;
                    $type = $item['scholarship_type'] ?? 'fixed';

                    if (is_numeric($scholarship) && (float) $scholarship > 0) {
                        if ($type === 'percent' && (float) $scholarship > 100) {
                            $validator->errors()->add(
                                "items.{$index}.scholarship_value",
                                'A scholarship percentage cannot exceed 100.'
                            );
                        }

                        if ($type === 'fixed' && is_numeric($amount) && (float) $scholarship > (float) $amount) {
                            $validator->errors()->add(
                                "items.{$index}.scholarship_value",
                                'A fixed scholarship cannot exceed the fee amount.'
                            );
                        }
                    }
                }
            },
        ];
    }
}
