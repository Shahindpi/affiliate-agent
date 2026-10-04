<?php

namespace App\Http\Controllers\Api\Admin\Agent;

use App\Http\Controllers\Controller;
use App\Models\Agent\Feedback;
use App\Models\Agent\Preference;
use App\Models\AffiliateProduct;
use App\Services\AffiliateAgent\ContentSchema;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PreferenceController extends Controller
{
    public function index() { return ApiResponse::success(Preference::latest()->get()); }
    public function store(Request $r)
    {
        return ApiResponse::success(Preference::create([...$this->validateData($r), 'created_by' => $r->user()->id]), 'Preference saved for future generation only.', 201);
    }
    public function update(Request $r, Preference $preference)
    {
        $preference->update($this->validateData($r));
        return ApiResponse::success($preference, 'Future preference updated. Existing content is unchanged.');
    }
    public function destroy(Preference $preference)
    {
        $preference->update(['enabled' => false]);
        return ApiResponse::success($preference, 'Preference disabled.');
    }
    private function validateData(Request $r): array
    {
        $d = $r->validate(['scope' => ['required', Rule::in(['global', 'brand', 'product'])], 'brand_id' => 'nullable|integer|exists:brands,id,deleted_at,NULL', 'affiliate_product_id' => 'nullable|integer|exists:affiliate_products,id,deleted_at,NULL', 'component' => ['required', Rule::in(['all', ...ContentSchema::COMPONENTS])], 'instruction' => 'required|string|max:6000', 'enabled' => 'required|boolean', 'source_feedback_id' => 'nullable|integer|exists:agent_feedback,id']);
        if ($d['scope'] === 'brand' && empty($d['brand_id'])) throw ValidationException::withMessages(['brand_id' => 'Select a brand for this scope.']);
        if ($d['scope'] === 'product' && empty($d['affiliate_product_id'])) throw ValidationException::withMessages(['affiliate_product_id' => 'Select a product for this scope.']);
        if ($d['scope'] === 'global') { $d['brand_id'] = null; $d['affiliate_product_id'] = null; }
        if ($d['scope'] === 'brand') $d['affiliate_product_id'] = null;
        if ($d['scope'] === 'product') $d['brand_id'] = AffiliateProduct::findOrFail($d['affiliate_product_id'])->brand_id;
        return $d;
    }
}
