<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    // Creates one admin and one sample customer. Existing users are left as they are.
    public function run(): void
    {
        $this->make('admin@example.com', 'Admin', 'admin123', 'admin');
        $this->make('customer@example.com', 'Sample Customer', 'customer123', 'customer', '9876543210');
    }

    // Role is set directly because it is not mass assignable.
    private function make(string $email, string $name, string $password, string $role, ?string $phone = null): void
    {
        $user = User::firstOrNew(['email' => $email]);

        if ($user->exists) {
            return;
        }

        $user->name = $name;
        $user->password = $password;
        $user->role = $role;
        $user->phone = $phone;
        $user->save();
    }
}
