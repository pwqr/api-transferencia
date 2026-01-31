<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\QueryException;
use RuntimeException;

class UserService
{
    public function create(array $data): User
    {
        try {
            return User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'cpf' => $this->onlyDigits($data['cpf']),
                'password' => Hash::make($data['password']),
                'type' => $data['type'],
                'balance' => $data['type'] === 'common' ? 100 : 0,
            ]);
        } catch (QueryException $e) {
            throw new RuntimeException('CPF ou email já cadastrado.', 409);
        }
    }

    private function onlyDigits(string $value): string
    {
        return preg_replace('/\D/', '', $value);
    }
}
