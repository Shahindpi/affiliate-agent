<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'username' => [
                'nullable',
                'alpha_dash',
                'max:50',
                Rule::unique('users', 'username')
                    ->ignore($this->user()->id),
            ],

            'phone' => [
                'nullable',
                'string',
                'max:30',
            ],

        ];
    }

    public function messages(): array
    {
        return [

            'username.unique' => 'Username already exists.',

            'username.alpha_dash' =>
                'Username may contain letters, numbers, dashes and underscores only.',

        ];
    }
}