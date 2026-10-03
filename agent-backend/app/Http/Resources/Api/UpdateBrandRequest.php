<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $brand = $this->route('brand');

        return [

            'name' => [
                'sometimes',
                'string',
                'max:150',
                Rule::unique('brands')->ignore($brand),
            ],

            'slug' => [
                'sometimes',
                'string',
                'max:170',
                Rule::unique('brands')->ignore($brand),
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
        ];
    }
}