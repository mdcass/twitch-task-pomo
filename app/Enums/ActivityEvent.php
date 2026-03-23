<?php

namespace App\Enums;

enum ActivityEvent: string
{
    case AuthLocalLoginFailed = 'auth.local.login_failed';
    case AuthLocalLoginSucceeded = 'auth.local.login_succeeded';
    case AuthLocalRegistrationCompleted = 'auth.local.registration_completed';
    case AuthSocialCallbackFailed = 'auth.social.callback_failed';
    case AuthSocialLoginMissingLink = 'auth.social.login_missing_link';
    case AuthSocialLoginSucceeded = 'auth.social.login_succeeded';
    case AuthSocialRegistrationBlockedExistingEmail = 'auth.social.registration_blocked_existing_email';
    case AuthSocialRegistrationCompleted = 'auth.social.registration_completed';
    case AuthSocialSessionInvalid = 'auth.social.session_invalid';
    case ProviderAuthCreated = 'provider_auth.created';
    case ProviderAuthUpdated = 'provider_auth.updated';
    case TeamCreated = 'team.created';
    case UserCreated = 'user.created';
    case UserUpdated = 'user.updated';
}
