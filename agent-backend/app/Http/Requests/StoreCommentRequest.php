<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'parent_id' => [
                'nullable',
                'integer',
                'exists:comments,id',
            ],

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

            'website' => [
                'nullable',
                'url',
                'max:255',
            ],

            'comment' => [
                'required',
                'string',
                'min:5',
                'max:3000',
            ],

        ];
    }

    public function messages(): array
    {
        return [

            'name.required' => 'Name is required.',

            'email.required' => 'Email address is required.',

            'email.email' => 'Please enter a valid email address.',

            'comment.required' => 'Comment is required.',

            'comment.min' => 'Comment must be at least 5 characters.',

            'parent_id.exists' => 'Reply comment does not exist.',

        ];
    }
}