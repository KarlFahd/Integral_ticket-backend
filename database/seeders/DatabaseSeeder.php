<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name'     => 'Karl',
            'username' => 'Karl',
            'role'     => 'employee',
            'is_admin' => false,
            'is_hr'    => false,
            'email'    => 'karl@integra.com',
            'password' => Hash::make('p@sswOrd'),
        ]);

        User::create([
            'name'     => 'Marc',
            'username' => 'Marc',
            'role'     => 'employee',
            'is_admin' => false,
            'is_hr'    => true,          // Marc is the HR user — can manage users & finance
            'email'    => 'marc@integra.com',
            'password' => Hash::make('p@sswOrd'),
        ]);

        User::create([
            'name'     => 'Karl Admin',
            'username' => 'karlADMIN',
            'role'     => 'admin',
            'is_admin' => true,
            'is_hr'    => false,
            'email'    => 'karladmin@integra.com',
            'password' => Hash::make('p@sswOrd'),
        ]);
    }
}
