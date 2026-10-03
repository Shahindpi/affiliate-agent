<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAffiliateProductRequest extends FormRequest
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
        $affiliateProduct = $this->route('affiliateProduct');

        return [
            'brand_id' => [
                'nullable',
                'integer',
                'exists:brands,id',
            ],

            'affiliate_network_id' => [
                'nullable',
                'integer',
                'exists:affiliate_networks,id',
            ],

            'category_id' => [
                'nullable',
                'integer',
                'exists:categories,id',
            ],

            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique(
                    'affiliate_products',
                    'slug'
                )->ignore($affiliateProduct?->id),
            ],

            'short_description' => [
                'nullable',
                'string',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'website_url' => [
                'nullable',
                'url',
                'max:2048',
            ],

            'affiliate_url' => [
                'sometimes',
                'required',
                'url',
                'max:2048',
            ],

            'price' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'currency' => [
                'nullable',
                'string',
                'max:10',
            ],

            'commission_rate' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],

            'free_trial' => [
                'nullable',
                'boolean',
            ],

            'rating' => [
                'nullable',
                'numeric',
                'min:0',
                'max:5',
            ],

            'featured_image' => [
                'nullable',
                'string',
                'max:255',
            ],

            'pros' => [
                'nullable',
                'array',
            ],

            'cons' => [
                'nullable',
                'array',
            ],

            'featured' => [
                'nullable',
                'boolean',
            ],

            'status' => [
                'nullable',
                'boolean',
            ],
        ];
    }
}