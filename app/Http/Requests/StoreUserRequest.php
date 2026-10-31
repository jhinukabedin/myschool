<?php

namespace App\Http\Requests;

use App\Models\Staff;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('users.manage') ?? false;
    }

    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'username' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:30'],
            'role_id' => ['required', 'exists:roles,id'],
            'staff_id' => [
                'nullable',
                'integer',
                Rule::exists('staff', 'id')->whereNull('deleted_at'),
                function (string $attribute, mixed $value, Closure $fail) use ($user) {
                    if (! $value) {
                        return;
                    }

                    $linkedUserId = Staff::query()->whereKey($value)->value('user_id');

                    if ($linkedUserId && (! $user || (int) $linkedUserId !== (int) $user->id)) {
                        $fail('That staff profile is already linked to another user account.');
                    }
                },
            ],
            'is_active' => ['nullable', 'boolean'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ];
    }
}
