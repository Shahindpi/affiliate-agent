<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('Development demo data is disabled in production.');
            return;
        }

        $this->call([
            RoleSeeder::class,
            AdminUserSeeder::class,
            DemoMediaSeeder::class,
            CategorySeeder::class,
            TagSeeder::class,
            BrandSeeder::class,
            AffiliateNetworkSeeder::class,
            AffiliateProductSeeder::class,
            HeroBannerSeeder::class,
            PostSeeder::class,
            CommentSeeder::class,
            NewsletterSeeder::class,
            SiteSettingSeeder::class,
            DemoAnalyticsSeeder::class,
        ]);
    }
}
