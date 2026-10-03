<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\AffiliateEvent;
use App\Models\AffiliateProduct;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AffiliateEventController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'affiliate_product_id' => ['required', 'integer', Rule::exists('affiliate_products', 'id')->where('status', true)->whereNull('deleted_at')],
            'kind' => ['required', Rule::in(['impression', 'click'])],
            'session_id' => ['required', 'uuid'],
            'placement' => ['nullable', 'string', 'max:60', 'regex:/^[a-z][a-z0-9_]*$/'],
        ]);
        $product = AffiliateProduct::query()->where('status', true)->findOrFail($data['affiliate_product_id']);
        $attributes = [
            'affiliate_product_id' => $product->id,
            'brand_id' => $product->brand_id,
            'affiliate_network_id' => $product->affiliate_network_id,
            'kind' => $data['kind'],
            'session_id' => $data['session_id'],
            'placement' => $data['placement'] ?? null,
        ];
        if ($data['kind'] === 'impression') {
            $event = AffiliateEvent::firstOrCreate([
                'affiliate_product_id' => $product->id, 'kind' => 'impression', 'session_id' => $data['session_id'],
            ], $attributes);
            return ApiResponse::success(['recorded' => $event->wasRecentlyCreated], 'Impression recorded.', $event->wasRecentlyCreated ? 201 : 200);
        }
        AffiliateEvent::create($attributes);
        return ApiResponse::success(['recorded' => true], 'Click recorded.', 201);
    }
}
