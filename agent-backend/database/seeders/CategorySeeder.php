<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (['AI Tools', 'Developer Tools', 'Productivity', 'Hosting', 'SaaS', 'Design Tools', 'Marketing', 'Security'] as $index => $name) {
            Category::updateOrCreate(['slug' => Str::slug($name)], [
                'name' => $name, 'description' => "Discover practical {$name} products and guides.",
                'image' => 'uploads/demo/product-'.(($index % 4) + 1).'.png',
                'status' => true, 'sort_order' => $index,
            ]);
        }
    }
}
