<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->slug === 'admin';
    }

    public function rules(): array
    {
        $roleId = $this->route('role')?->getKey();

        return [
            'name' => ['required', 'string', 'max:50', Rule::unique('roles', 'name')->ignore($roleId)],
            'slug' => ['required', 'alpha_dash', 'max:50', Rule::unique('roles', 'slug')->ignore($roleId)],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'boolean'],
        ];
    }
}
