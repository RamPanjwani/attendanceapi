<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $role = Role::create(['name' => 'management']);
        $role = Role::create(['name' => 'employee']);
        $role = Role::create(['name' => 'admin']);
        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@gmail.com',
            'password' => 'admin@1234', // Securely hash the password
        ]);

        // Assign role to the user
        $admin->assignRole('admin');
    }
}
