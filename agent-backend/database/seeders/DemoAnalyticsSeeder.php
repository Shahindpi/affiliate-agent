<?php

namespace Database\Seeders;

use App\Models\AffiliateEvent;
use App\Models\AffiliateProduct;
use Illuminate\Database\Seeder;

class DemoAnalyticsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (AffiliateProduct::orderBy('id')->get() as $product) {
            for ($index = 1; $index <= 3; $index++) {
                $session = sprintf('00000000-0000-4000-8000-%012d', $product->id * 10 + $index);
                foreach (['impression', ...($index < 3 ? ['click'] : [])] as $kind) {
                    $event = AffiliateEvent::firstOrCreate([
                        'affiliate_product_id' => $product->id,
                        'session_id' => $session,
                        'kind' => $kind,
                    ], ['brand_id' => $product->brand_id, 'is_demo' => true]);
                    $event->update(['is_demo' => true]);
                    if ($event->wasRecentlyCreated) $event->forceFill(['created_at' => now()->subDays($index)])->save();
                }
            }
            // A varied click history makes the popular list meaningfully different from latest.
            for ($index = 1; $index <= ($product->id % 5); $index++) {
                AffiliateEvent::updateOrCreate([
                    'affiliate_product_id' => $product->id,
                    'session_id' => sprintf('00000000-0000-4000-8000-%012d', 100000 + $product->id * 10 + $index),
                    'kind' => 'click',
                ], ['brand_id' => $product->brand_id, 'affiliate_network_id' => $product->affiliate_network_id, 'placement' => 'homepage_popular', 'is_demo' => true]);
            }
        }
    }
}
