<?php

namespace App\Actions\Auth;

use App\Actions\Fortify\CreateNewUser;
use App\Enums\ExternalAuthProvider;
use App\Enums\UserSettingKey;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Jetstream;

class CompleteSocialRegistration
{
    public function __construct(
        private readonly CreateNewUser $createNewUser,
    ) {}

    public function validateEmail(string $email): string
    {
        $validated = Validator::make([
            'email' => $this->normalizeEmail($email),
        ], [
            'email' => ['required', 'string', 'email', 'max:255'],
        ])->validate();

        return $validated['email'];
    }

    public function emailBelongsToExistingUser(string $email): bool
    {
        return User::query()
            ->whereRaw('LOWER(email) = ?', [$this->normalizeEmail($email)])
            ->exists();
    }

    /**
     * @param  array{
     *     name:string,
     *     email:string,
     *     provider:ExternalAuthProvider|string,
     *     provider_user_id:string,
     *     provider_email:?string,
     *     avatar_url:?string,
     *     access_token:string,
     *     refresh_token:?string,
     *     expires_in:?int,
     *     scopes?:list<string>,
     *     profile?:array<string, mixed>,
     *     legal_acceptance?:?array<string, string>
     * }  $input
     *
     * @throws ValidationException
     */
    public function complete(array $input): User
    {
        $input['email'] = $this->normalizeEmail($input['email'] ?? null);
        $input['provider_email'] = $this->normalizeEmail($input['provider_email'] ?? null);

        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'provider' => ['required', new Enum(ExternalAuthProvider::class)],
            'provider_user_id' => ['required', 'string', 'max:255'],
            'provider_email' => ['nullable', 'string', 'email', 'max:255'],
            'avatar_url' => ['nullable', 'string', 'url', 'starts_with:https://'],
            'access_token' => ['required', 'string'],
            'refresh_token' => ['nullable', 'string'],
            'expires_in' => ['nullable', 'integer', 'min:0'],
            'scopes' => ['sometimes', 'array'],
            'scopes.*' => ['string'],
            'profile' => ['sometimes', 'array'],
            'legal_acceptance' => ['nullable', 'array'],
        ])->validate();

        if ($this->emailBelongsToExistingUser($validated['email'])) {
            throw ValidationException::withMessages([
                'email' => 'That email already belongs to an existing account.',
            ]);
        }

        if (trim($validated['access_token']) === '') {
            throw ValidationException::withMessages([
                'access_token' => 'A provider access token is required to complete registration.',
            ]);
        }

        return DB::transaction(function () use ($validated): User {
            $user = $this->createNewUser->createForSocialRegistration([
                'name' => $validated['name'],
                'email' => $validated['email'],
            ]);

            $user->providerAuths()->create([
                'provider' => $validated['provider'],
                'provider_user_id' => $validated['provider_user_id'],
                'provider_email' => $validated['provider_email'] ?? null,
                'avatar_url' => $validated['avatar_url'] ?? null,
                'access_token' => $validated['access_token'] ?? null,
                'refresh_token' => $validated['refresh_token'] ?? null,
                'token_expires_at' => isset($validated['expires_in']) ? now()->addSeconds((int) $validated['expires_in']) : null,
                'scopes' => $validated['scopes'] ?? [],
                'profile' => $validated['profile'] ?? [],
                'last_used_at' => now(),
            ]);

            $this->recordLegalAcceptance($user, $validated['legal_acceptance'] ?? null);

            return $user;
        });
    }

    /**
     * @param  array<string, string>|null  $legalAcceptance
     */
    private function recordLegalAcceptance(User $user, ?array $legalAcceptance): void
    {
        if (! Jetstream::hasTermsAndPrivacyPolicyFeature() || $legalAcceptance === null) {
            return;
        }

        $acceptedAt = now()->toIso8601String();
        $termsAcceptedAt = $legalAcceptance['terms_of_service_accepted_at'] ?? $acceptedAt;
        $privacyAcceptedAt = $legalAcceptance['privacy_policy_accepted_at'] ?? $acceptedAt;
        $version = now()->toDateString();

        $user->userSettings()->updateOrCreate(
            ['key' => UserSettingKey::LegalAcceptanceHistory],
            [
                'value' => [
                    'current' => [
                        'terms_of_service_accepted_at' => $termsAcceptedAt,
                        'privacy_policy_accepted_at' => $privacyAcceptedAt,
                    ],
                    'history' => [
                        [
                            'document' => 'terms_of_service',
                            'version' => $version,
                            'accepted_at' => $termsAcceptedAt,
                            'source' => 'social_register',
                            'source_metadata' => [
                                'provider' => 'socialite',
                                'route' => 'register',
                            ],
                        ],
                        [
                            'document' => 'privacy_policy',
                            'version' => $version,
                            'accepted_at' => $privacyAcceptedAt,
                            'source' => 'social_register',
                            'source_metadata' => [
                                'provider' => 'socialite',
                                'route' => 'register',
                            ],
                        ],
                    ],
                ],
            ],
        );
    }

    private function normalizeEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $normalized = Str::lower(trim($email));

        return $normalized !== '' ? $normalized : null;
    }
}
