<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('exams.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'academic_session_id' => ['required', 'exists:academic_sessions,id'],
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('examinations', 'name')
                    ->where('academic_session_id', $this->input('academic_session_id'))
                    ->ignore($this->route('exam')),
            ],
            'exam_type' => ['required', 'string', 'max:60'],
            'grading_scheme_id' => ['nullable', 'exists:grading_schemes,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(['draft', 'published', 'completed'])],
        ];
    }
}
