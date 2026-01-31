<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
    User::create([
        'name' => 'Comum',
        'email' => 'comum@test.com',
        'cpf' => '12345678901',
        'password' => bcrypt('password'),
        'type' => 'common',
        'balance' => 1000,
    ]);

    User::create([
        'name' => 'Lojista',
        'email' => 'lojista@test.com',
        'cpf' => '12345678902',
        'password' => bcrypt('password'),
        'type' => 'merchant',
        'balance' => 0,
    ]);
    }
}
