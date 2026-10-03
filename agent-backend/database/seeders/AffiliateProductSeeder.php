<?php

namespace Database\Seeders;

use App\Models\AffiliateNetwork;
use App\Models\AffiliateProduct;
use App\Models\Brand;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AffiliateProductSeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            'Northstar Writing Desk', 'Cedar Deployment Hub', 'Fern Design Library', 'Peak Task Planner',
            'Dawn Insights Studio', 'Summit Password Vault', 'Arc Prototype Kit', 'Harbor Cloud Workspace',
            'Northstar Research Companion', 'Cedar Developer Console', 'Fern Creative Canvas', 'Peak Team Calendar',
            'Dawn Marketing Reports', 'Summit Privacy Monitor', 'Arc Interface Builder', 'Harbor Site Manager',
        ];
        $categories = Category::orderBy('sort_order')->get();
        $brands = Brand::orderBy('id')->get();
        $networks = AffiliateNetwork::orderBy('id')->get();
        foreach ($names as $index => $name) {
            $product = AffiliateProduct::updateOrCreate(['slug' => Str::slug($name)], [
                'name' => $name, 'brand_id' => $brands[$index % $brands->count()]->id,
                'category_id' => $categories[$index % $categories->count()]->id,
                'affiliate_network_id' => $networks[$index % $networks->count()]->id,
                'short_description' => "Explore {$name} for practical workflows and everyday projects.",
                'description' => "<p>{$name} is a demo product used to explore categories, offers and buying guides.</p>",
                'featured_image' => 'uploads/demo/product-'.(($index % 4) + 1).'.png',
                'affiliate_url' => 'https://example.com/affiliate/'.Str::slug($name),
                'website_url' => 'https://example.com/products/'.Str::slug($name),
                'price' => 19 + ($index * 4), 'currency' => 'USD',
                'featured' => $index < 6, 'status' => true,
                'pros' => ['Clear setup', 'Practical features'], 'cons' => ['Evaluate fit before purchase'],
            ]);
            $product->forceFill(['created_at' => now()->subDays(16 - $index)])->save();
            if ($index < 3) {
                $product->seoMeta()->updateOrCreate([], [
                    'meta_title' => $name.' | Dewdora demo',
                    'meta_description' => "Explore {$name} and discover whether it fits your workflow.",
                ]);
            }
        }
    }
}
