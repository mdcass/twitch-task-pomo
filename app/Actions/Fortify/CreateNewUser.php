<?php

namespace App\Actions\Fortify;

use App\Actions\Teams\CreateOwnedTeam;
use App\Enums\TeamType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => $this->passwordRules(),
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['accepted', 'required'] : '',
        ])->validate();

        return $this->persist(
            attributes: [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'email_verified_at' => null,
            ],
            createOwnedTeam: true,
        );
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function createForSocialRegistration(array $input): User
    {
        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
        ])->validate();

        return $this->persist(
            attributes: [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Str::password(32),
                'email_verified_at' => null,
            ],
            createOwnedTeam: true,
        );
    }

    /**
     * @param  array{name:string,email:string,password:string,email_verified_at:mixed}  $attributes
     */
    private function persist(array $attributes, bool $createOwnedTeam): User
    {
        return DB::transaction(function () use ($attributes, $createOwnedTeam): User {
            $user = new User();
            $user->forceFill([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'password' => Hash::make($attributes['password']),
                'email_verified_at' => $attributes['email_verified_at'],
            ])->save();

            if ($createOwnedTeam) {
                app(CreateOwnedTeam::class)->create($user, TeamType::Streamer);
            }

            return $user;
        });
    }
}
