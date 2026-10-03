<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'path' => [
                'required',
                'string',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'path.required' => 'Image path is required.',
        ];
    }
}