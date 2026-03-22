<?php

namespace Database\Factories;

use App\Enums\UserSettingKey;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserSetting>
 */
class UserSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $termsAcceptedAt = now()->subMinute()->toIso8601String();
        $privacyAcceptedAt = now()->toIso8601String();

        return [
            'user_id' => User::factory(),
            'key' => UserSettingKey::LegalAcceptanceHistory,
            'value' => [
                'current' => [
                    'terms_of_service_accepted_at' => $termsAcceptedAt,
                    'privacy_policy_accepted_at' => $privacyAcceptedAt,
                ],
                'history' => [
                    [
                        'document' => 'terms_of_service',
                        'version' => '2026-03-22',
                        'accepted_at' => $termsAcceptedAt,
                        'source' => 'register',
                        'source_metadata' => [
                            'route' => 'register',
                        ],
                    ],
                    [
                        'document' => 'privacy_policy',
                        'version' => '2026-03-22',
                        'accepted_at' => $privacyAcceptedAt,
                        'source' => 'register',
                        'source_metadata' => [
                            'route' => 'register',
                        ],
                    ],
                ],
            ],
        ];
    }
}
