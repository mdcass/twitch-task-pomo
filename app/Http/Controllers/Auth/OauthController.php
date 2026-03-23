<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\SocialAuthService;
use App\Enums\ExternalAuthProvider;
use App\Enums\OauthFlow;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Laravel\Jetstream\Jetstream;

class OauthController extends Controller
{
    public function __construct(
        private readonly SocialAuthService $socialAuth,
    ) {}

    public function redirect(Request $request, ExternalAuthProvider $provider): RedirectResponse
    {
        $validator = Validator::make($request->query(), [
            'flow' => ['required', new Enum(OauthFlow::class)],
        ]);

        $validator->sometimes('terms', ['accepted', 'required'], fn ($input): bool => Jetstream::hasTermsAndPrivacyPolicyFeature()
                && $input->flow === OauthFlow::Register->value);

        $validator->setCustomMessages([
            'terms.accepted' => 'You must accept the Terms of Service and Privacy Policy before signing up with '.$provider->label().'.',
        ]);

        $validator->sometimes('debug', [
            'string',
            Rule::anyOf([
                Rule::in(['no_email']),
                'email',
            ]),
        ], fn () => $this->socialAuth->shouldUseDebugEmailOverride());

        $validated = $validator->validate();
        $flow = OauthFlow::from($validated['flow']);
        $legalAcceptance = null;
        $debugOverride = $this->socialAuth->shouldUseDebugEmailOverride()
            ? ($validated['debug'] ?? null)
            : null;

        if ($flow === OauthFlow::Register && Jetstream::hasTermsAndPrivacyPolicyFeature()) {
            $acceptedAt = now()->toIso8601String();

            $legalAcceptance = [
                'terms_of_service_accepted_at' => $acceptedAt,
                'privacy_policy_accepted_at' => $acceptedAt,
            ];
        }

        return $this->socialAuth->redirect($request, $provider, $flow, $legalAcceptance, $debugOverride);
    }

    public function callback(Request $request, ExternalAuthProvider $provider): RedirectResponse
    {
        return $this->socialAuth->callback($request, $provider);
    }
}
