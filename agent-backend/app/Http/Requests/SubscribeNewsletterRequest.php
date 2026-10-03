<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubscribeNewsletterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'email' => [
                'required',
                'email:rfc',
                'max:255',
            ],

            'name' => [
                'nullable',
                'string',
                'max:100',
            ],

        ];
    }

    public function messages(): array
    {
        return [

            'email.required' => 'Email address is required.',

            'email.email' => 'Please enter a valid email address.',

        ];
    }
}