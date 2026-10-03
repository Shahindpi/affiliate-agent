<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['Administrator', 'admin', 'Full CMS access'],
            ['Editor', 'editor', 'Editorial account'],
            ['Author', 'author', 'Contributor account'],
            ['Viewer', 'viewer', 'Read-only account'],
        ] as [$name, $slug, $description]) {
            Role::updateOrCreate(['slug' => $slug], compact('name', 'slug', 'description') + ['status' => true]);
        }
    }
}
