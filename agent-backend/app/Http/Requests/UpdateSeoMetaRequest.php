<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSeoMetaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'meta_title' => [
                'nullable',
                'string',
                'max:255',
            ],

            'meta_description' => [
                'nullable',
                'string',
                'max:500',
            ],

            'canonical_url' => [
                'nullable',
                'url',
                'max:2048',
            ],

            'og_title' => [
                'nullable',
                'string',
                'max:255',
            ],

            'og_description' => [
                'nullable',
                'string',
                'max:500',
            ],

            'og_image' => [
                'nullable',
                'string',
                'max:2048',
            ],

            'twitter_title' => [
                'nullable',
                'string',
                'max:255',
            ],

            'twitter_description' => [
                'nullable',
                'string',
                'max:500',
            ],

            'twitter_image' => [
                'nullable',
                'string',
                'max:2048',
            ],

            'robots' => [
                'nullable',
                'string',
                'max:100',
            ],

            'schema_data' => [
                'nullable',
                'array',
            ],
        ];
    }
}