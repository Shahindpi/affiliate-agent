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
        $affiliateNetwork = $this->route('affiliateNetwork');

        return [

            'name' => [
                'sometimes',
                'string',
                'max:150',
                Rule::unique('affiliate_networks', 'name')
                    ->ignore($affiliateNetwork),
            ],

            'slug' => [
                'sometimes',
                'string',
                'max:170',
                Rule::unique('affiliate_networks', 'slug')
                    ->ignore($affiliateNetwork),
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
            'status' => ['nullable', 'boolean'],
        ];
    }
}
