<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TagSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Automation', 'AI Writing', 'Workflow', 'Code Editors', 'Web Hosting', 'Cloud', 'Design Systems', 'Analytics', 'Privacy', 'Teamwork', 'Content', 'Email', 'Research', 'Security', 'Comparison'] as $name) {
            Tag::updateOrCreate(['slug' => Str::slug($name)], ['name' => $name]);
        }
    }
}
