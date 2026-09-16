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

        $systemOwnerPassword = env('SYSTEM_OWNER_PASSWORD');

        if (! $systemOwnerPassword) {
            throw new \RuntimeException('SYSTEM_OWNER_PASSWORD must be set before running DatabaseSeeder.');
        }

        $systemOwner = User::firstOrCreate(
            ['email' => 'shabanshaheen564@gmail.com'],
            [
                'name' => 'Eng.Shaban Shaheen',
                'password' => Hash::make($systemOwnerPassword),
                'is_active' => true,
            ]
        );

        $systemOwnerRole = Role::where('name', 'System Owner')->first();
        if ($systemOwnerRole) {
            $systemOwner->syncRoles([$systemOwnerRole]);
        }
    }
}
