<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreBrandRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->filled('slug') && $this->filled('name')) {
            $this->merge(['slug' => Str::slug($this->string('name')->value())]);
        }
    }

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
                'unique:brands,name',
            ],

            'slug' => [
                'nullable',
                'string',
                'max:170',
                'unique:brands,slug',
            ],

            'website' => [
                'nullable',
                'url',
                'max:255',
            ],

            'logo' => [
                'nullable',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
            ],
            'status' => ['nullable', 'boolean'],
        ];
    }
}
