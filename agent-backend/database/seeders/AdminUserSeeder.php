<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(['email' => 'admin@example.com'], [
            'name' => 'Admin', 'username' => 'admin', 'role_id' => Role::where('slug', 'admin')->firstOrFail()->id,
            'password' => 'Admin@1234567', 'status' => true,
        ]);
        foreach ([['Editor', 'editor'], ['Author', 'author'], ['Viewer', 'viewer']] as [$name, $slug]) {
            User::updateOrCreate(['email' => $slug.'@example.test'], [
                'name' => 'Demo '.$name, 'username' => 'demo-'.$slug,
                'password' => 'DemoPassword!2026', 'role_id' => Role::where('slug', $slug)->firstOrFail()->id,
                'status' => true,
            ]);
        }
    }
}
