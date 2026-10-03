<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'avatar' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048', // 2MB
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'avatar.required' => 'Please select an avatar image.',
            'avatar.image' => 'Avatar must be an image.',
            'avatar.max' => 'Avatar size may not exceed 2MB.',
        ];
    }
}