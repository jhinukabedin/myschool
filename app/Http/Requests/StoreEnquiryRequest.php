<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEnquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('enquiries.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'student_name' => ['required', 'string', 'max:120'],
            'guardian_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'class_id' => ['nullable', 'exists:classes,id'],
            'follow_up_date' => ['nullable', 'date'],
            'source' => ['nullable', 'string', 'max:60'],
            'status' => ['required', 'in:new,contacted,follow_up,admitted,closed'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
