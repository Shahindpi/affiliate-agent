<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBrandRequest extends FormRequest
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
        ];
    }
}