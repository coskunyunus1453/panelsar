<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@hostvim.local'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $admin->forceFill(['is_admin' => true])->save();

        $this->call(LandingSettingsSeeder::class);
        $this->call(ContentSeeder::class);
        $this->call(NavMenuSeeder::class);
        $this->call(SaasBootstrapSeeder::class);
    }
}
