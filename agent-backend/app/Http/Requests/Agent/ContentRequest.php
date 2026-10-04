<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class ContentRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->role?->slug === 'admin'; }
    public function rules(): array
    {
        return ['title' => 'required|string|max:255', 'brand_id' => 'nullable|integer|exists:brands,id,deleted_at,NULL', 'affiliate_product_id' => 'nullable|integer|exists:affiliate_products,id,deleted_at,NULL', 'snapshot' => 'required|array'];
    }
}
