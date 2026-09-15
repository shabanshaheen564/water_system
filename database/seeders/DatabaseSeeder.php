<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        // Create the real System Owner (not a demo account)
        $systemOwner = User::firstOrCreate(
            ['email' => 'shabanshaheen564@gmail.com'],
            [
                'name' => 'Eng.Shaban Shaheen',
                'password' => Hash::make('12345678'),
                'is_active' => true,
            ]
        );

        $systemOwnerRole = Role::where('name', 'System Owner')->first();
        if ($systemOwnerRole) {
            $systemOwner->syncRoles([$systemOwnerRole]);
        }
    }
}
