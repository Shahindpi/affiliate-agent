<?php

namespace Database\Seeders;

use App\Models\NewsletterSubscriber;
use Illuminate\Database\Seeder;

class NewsletterSeeder extends Seeder
{
    public function run(): void
    {
        foreach (range(1, 5) as $index) {
            NewsletterSubscriber::updateOrCreate(['email' => "demo-subscriber-{$index}@example.test"], [
                'name' => "Demo Subscriber {$index}", 'status' => $index !== 5,
                'subscribed_at' => now()->subDays($index),
                'unsubscribed_at' => $index === 5 ? now() : null,
            ]);
        }
    }
}
