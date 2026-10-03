<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Northstar Labs', 'Cedar Cloud', 'Fern Studio', 'Peak Productivity', 'Dawn Analytics', 'Summit Secure', 'Arc Design', 'Harbor Hosting'] as $index => $name) {
            Brand::updateOrCreate(['slug' => Str::slug($name)], [
                'name' => $name, 'description' => "Demo {$name} products for evaluating Dewdora.",
                'website' => 'https://example.com/brands/'.Str::slug($name),
                'logo' => 'uploads/demo/brand-'.($index + 1).'.png', 'status' => true,
            ]);
        }
    }
}
