<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNoticeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('notices.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:20000'],
            'body_format' => ['nullable', Rule::in(['text', 'html'])],
            'audience' => ['required', Rule::in(['all', 'students', 'staff', 'classes'])],
            'classes' => ['required_if:audience,classes', 'array'],
            'classes.*' => ['exists:classes,id'],
            'publish_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:publish_at'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
        ];
    }
}
