<x-guest-layout variant="card">
    @php
        $showLegalAcceptance = Laravel\Jetstream\Jetstream::hasTermsAndPrivacyPolicyFeature();
        $termsUrl = route('terms.show');
        $policyUrl = route('policy.show');
    @endphp

    <x-auth.page-card>
        <x-slot name="aside">
            <div class="position-relative px-4 px-lg-7 pt-7 pb-7 pb-sm-5 text-center text-md-start pb-lg-7 card-sign-up">
                <h3 class="mb-3 text-body-emphasis fs-7">Register for your overlay composers and stream tools</h3>
                <p class="text-body-tertiary">Register with your email, Twitch, or Discord. Get started in under 2 minutes.</p>
                <ul class="list-unstyled mb-0 w-max-content w-md-auto">
                    <li class="d-flex align-items-center"><span class="fa-solid fa-check text-success me-2"></span><span class="text-body-tertiary fw-semibold">Unlimited overlay composers</span></li>
                    <li class="d-flex align-items-center"><span class="fa-solid fa-check text-success me-2"></span><span class="text-body-tertiary fw-semibold">Themed pomo timers and task bots</span></li>
                    <li class="d-flex align-items-center"><span class="fa-solid fa-check text-success me-2"></span><span class="text-body-tertiary fw-semibold">Custom bot commands</span></li>
                </ul>
            </div>
            <div class="position-relative z-n1 mb-6 d-none d-md-block text-center mt-md-15">
                <img
                    class="auth-title-box-img"
                    src="{{ asset('images/auth/phoenix-auth-illustration.png') }}"
                    alt=""
                />
            </div>
        </x-slot>

        <div class="text-center mb-7">
            <x-auth.simple-brand mark-only class="mb-1" mark-class="p-1" />
            <h3 class="text-body-highlight">{{ __('Sign Up') }}</h3>
            <p class="text-body-tertiary">Create your account today.</p>
        </div>

        <x-validation-errors class="mb-4" />

        <form method="GET" action="{{ route('oauth.redirect', ['provider' => 'twitch']) }}">
            <input type="hidden" name="flow" value="register" />

            @if ($showLegalAcceptance)
                <div class="form-check mb-4">
                    <input
                        id="social_terms"
                        name="terms"
                        type="checkbox"
                        value="1"
                        class="form-check-input"
                        @checked(old('terms'))
                    />
                    <label class="form-check-label small text-body-secondary" for="social_terms">
                        {!! __('I agree to the :terms and :privacy', [
                            'terms' => '<a target="_blank" href="'.$termsUrl.'" class="fw-semibold text-decoration-none">'.__('Terms of Service').'</a>',
                            'privacy' => '<a target="_blank" href="'.$policyUrl.'" class="fw-semibold text-decoration-none">'.__('Privacy Policy').'</a>',
                        ]) !!}
                    </label>
                </div>
            @endif

            <x-auth.social-button as="button" type="submit" icon="fa-brands fa-twitch" iconColorClass="text-primary" class="mb-3">
                {{ __('Sign up with Twitch') }}
            </x-auth.social-button>

            <x-auth.social-button
                as="button"
                type="submit"
                icon="fa-brands fa-discord"
                iconColorClass="text-info"
                class="mb-4"
                formaction="{{ route('oauth.redirect', ['provider' => 'discord']) }}"
            >
                {{ __('Sign up with Discord') }}
            </x-auth.social-button>
        </form>

        <div class="position-relative mt-4">
            <hr class="bg-body-secondary" />
            <div class="divider-content-center bg-body-emphasis">{{ __('or continue with email') }}</div>
        </div>

        <form method="POST" action="{{ route('register') }}" class="mt-4">
            @csrf

            <x-auth.field
                id="name"
                name="name"
                :label="__('Name')"
                type="text"
                :value="old('name')"
                required
                autofocus
                autocomplete="name"
            />

            <x-auth.field
                id="email"
                name="email"
                :label="__('Email address')"
                type="email"
                :value="old('email')"
                required
                autocomplete="username"
            />

            <x-auth.field-row>
                <x-auth.field-column>
                    <x-auth.field
                        id="password"
                        name="password"
                        :label="__('Password')"
                        type="password"
                        class="mb-0"
                        required
                        autocomplete="new-password"
                    />
                </x-auth.field-column>

                <x-auth.field-column>
                    <x-auth.field
                        id="password_confirmation"
                        name="password_confirmation"
                        :label="__('Confirm Password')"
                        type="password"
                        class="mb-0"
                        required
                        autocomplete="new-password"
                    />
                </x-auth.field-column>
            </x-auth.field-row>

            @if ($showLegalAcceptance)
                <div class="form-check mb-4">
                    <input
                        id="terms"
                        name="terms"
                        type="checkbox"
                        value="1"
                        class="form-check-input"
                        required
                        @checked(old('terms'))
                    />
                    <label class="form-check-label small text-body-secondary" for="terms">
                        {!! __('I agree to the :terms and :privacy', [
                            'terms' => '<a target="_blank" href="'.$termsUrl.'" class="fw-semibold text-decoration-none">'.__('Terms of Service').'</a>',
                            'privacy' => '<a target="_blank" href="'.$policyUrl.'" class="fw-semibold text-decoration-none">'.__('Privacy Policy').'</a>',
                        ]) !!}
                    </label>
                </div>
            @endif

            <x-button class="w-100 mb-3">{{ __('Sign up') }}</x-button>

            <div class="text-center">
                <a class="fs-9 fw-bold" href="{{ route('login') }}">
                    {{ __('Sign in to an existing account') }}
                </a>
            </div>
        </form>
    </x-auth.page-card>
</x-guest-layout>
