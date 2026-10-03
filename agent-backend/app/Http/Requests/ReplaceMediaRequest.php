<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReplaceMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'image' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            'old_path' => [
                'nullable',
                'string',
            ],

        ];
    }

    public function messages(): array
    {
        return [
            'image.required' => 'Please select a replacement image.',
            'image.image' => 'Uploaded file must be an image.',
            'image.mimes' => 'Allowed formats: JPG, JPEG, PNG, WebP.',
            'image.max' => 'Maximum image size is 5 MB.',
        ];
    }
}