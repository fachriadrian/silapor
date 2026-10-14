<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'unit' => ['required', 'string', 'max:100'],
            'password' => $this->passwordRules(),
        ])->validate();

        // 'role' dan 'is_active' sengaja tidak diisi dari input: user baru
        // selalu berperan 'user' dan aktif (nilai default di database).
        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'unit' => $input['unit'],
            'password' => $input['password'],
        ]);
    }
}
