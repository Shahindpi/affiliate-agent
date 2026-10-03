<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DemoMediaSeeder extends Seeder
{
    public function run(): void
    {
        foreach (glob(__DIR__.'/assets/*.png') as $file) {
            Storage::disk('public')->put('uploads/demo/'.basename($file), file_get_contents($file));
        }
    }
}
