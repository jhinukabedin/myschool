<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMarksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('marks.enter') ?? false;
    }

    public function rules(): array
    {
        return [
            'examination_subject_id' => ['required', 'exists:examination_subjects,id'],
            'marks' => ['nullable', 'array'],
            'marks.*' => ['nullable', 'numeric', 'min:0'],
            'components' => ['nullable', 'array'],
            'components.*' => ['array'],
            'components.*.*' => ['nullable', 'numeric', 'min:0'],
            'absent' => ['nullable', 'array'],
            'absent.*' => ['nullable', 'boolean'],
            'special_status' => ['nullable', 'array'],
            'special_status.*' => ['nullable', 'string', 'in:absent,exempt,withheld,incomplete,disqualified'],
            'correction_reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
