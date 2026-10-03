<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContactMessageRequest extends FormRequest
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

            'email' => [
                'required',
                'email:rfc',
                'max:255',
            ],

            'subject' => [
                'required',
                'string',
                'max:200',
            ],

            'message' => [
                'required',
                'string',
                'min:10',
                'max:5000',
            ],

        ];
    }

    public function messages(): array
    {
        return [

            'name.required' => 'Your name is required.',

            'email.required' => 'Email address is required.',

            'email.email' => 'Please enter a valid email address.',

            'subject.required' => 'Subject is required.',

            'message.required' => 'Message is required.',

            'message.min' => 'Message must be at least 10 characters.',

        ];
    }
}