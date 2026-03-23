@component('mail::message')
    {{ __('You have been invited to join :team on :product.', ['team' => $invitation->team->name, 'product' => \App\Support\Branding\ProductBrand::productName()]) }}

    @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::registration()))
        {{ __('Create your account first if you are new here, then return to accept the invitation.') }}

        @component('mail::button', ['url' => route('register')])
            {{ __('Create Account') }}
        @endcomponent

        {{ __('If you already have an account, you can accept the invitation right away:') }}
    @else
        {{ __('You can accept the invitation by clicking the button below:') }}
    @endif


    @component('mail::button', ['url' => $acceptUrl])
        {{ __('Accept Invitation') }}
    @endcomponent

    {{ __('If you were not expecting this invitation, you can safely ignore this email.') }}
@endcomponent
