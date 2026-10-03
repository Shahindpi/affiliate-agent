<?php

namespace Database\Seeders;

use App\Models\AffiliateNetwork;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AffiliateNetworkSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Direct', 'Impact', 'PartnerStack', 'CJ', 'ShareASale'] as $name) {
            AffiliateNetwork::updateOrCreate(['slug' => Str::slug($name)], [
                'name' => $name, 'description' => "Demo {$name} affiliate network.",
                'website' => 'https://example.com/networks/'.Str::slug($name), 'status' => true,
            ]);
        }
    }
}
