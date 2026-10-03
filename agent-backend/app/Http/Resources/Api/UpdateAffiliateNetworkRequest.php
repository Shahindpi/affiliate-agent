<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAffiliateNetworkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $network = $this->route('affiliate_network');

        return [

            'name' => [
                'sometimes',
                'string',
                'max:150',
                Rule::unique('affiliate_networks', 'name')
                    ->ignore($network),
            ],

            'slug' => [
                'sometimes',
                'string',
                'max:170',
                Rule::unique('affiliate_networks', 'slug')
                    ->ignore($network),
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