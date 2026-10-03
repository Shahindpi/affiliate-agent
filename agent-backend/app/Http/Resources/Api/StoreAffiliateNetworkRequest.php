<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAffiliateNetworkRequest extends FormRequest
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
                'unique:affiliate_networks,name',
            ],

            'slug' => [
                'nullable',
                'string',
                'max:170',
                'unique:affiliate_networks,slug',
            ],

            'website' => [
                'nullable',
                'url',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
            ],
        ];
    }
}