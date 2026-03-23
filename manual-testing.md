# Manual Testing Guide

## Scope

- Commits under review:
  - `37a2bd63` - `feat: BP-5 Twitch and Discord authentication`
  - `4508a95c` - `feat: BP-5 No social auth email address workflow`
- Combined feature area:
  - Twitch and Discord social sign-in
  - social registration from provider callbacks
  - guest workflow recovery when the provider does not supply an email address
  - blocked handoff when a provider email matches an existing local account

## Reviewer Setup

- Start from a current local database with migrations applied.
- Ensure the app can serve the guest auth routes locally.
- Configure OAuth credentials before attempting real provider round-trips:
  - `TWITCH_CLIENT_ID`
  - `TWITCH_CLIENT_SECRET`
  - `TWITCH_REDIRECT_URI`
  - `DISCORD_CLIENT_ID`
  - `DISCORD_CLIENT_SECRET`
  - `DISCORD_REDIRECT_URI`
- Keep terms/privacy enabled if you want to verify the registration gate exactly as shipped.
- Use a local or debug environment if you want to exercise the `debug` override paths.
- Have a way to inspect the database while testing:
  - `users`
  - `provider_auths`
  - `user_settings`
  - `workflow_stores`

## High-Value Files To Review

### Auth Entry And Routing

- `routes/web.php`
- `app/Http/Controllers/Auth/OauthController.php`
- `resources/views/auth/login.blade.php`
- `resources/views/auth/register.blade.php`
- `resources/views/components/auth/social-button.blade.php`
- `app/Enums/ExternalAuthProvider.php`
- `app/Enums/OauthFlow.php`

### Social Auth And Registration Completion

- `app/Actions/Auth/SocialAuthService.php`
- `app/Actions/Auth/CompleteSocialRegistration.php`
- `app/Actions/Fortify/CreateNewUser.php`

### Missing-Email Workflow Infrastructure

- `app/Contracts/Workflow.php`
- `app/Workflows/BaseWorkflow.php`
- `app/Workflows/ArrayWorkflow.php`
- `app/Workflows/Auth/SocialRegistrationWorkflow.php`
- `app/Livewire/Workflows/Concerns/LivewireWorkflow.php`
- `app/Models/WorkflowStore.php`
- `database/migrations/2026_03_22_000003_create_workflow_stores_table.php`

### Missing-Email Recovery UI And Coverage

- `app/Livewire/Auth/SocialRegistrationEmailForm.php`
- `resources/views/livewire/auth/social-registration-email-form.blade.php`
- `tests/Feature/SocialAuthenticationTest.php`
- `tests/Feature/SocialRegistrationEmailFormTest.php`
- `tests/Feature/Workflows/BaseWorkflowTest.php`
- `tests/Feature/Workflows/ArrayWorkflowTest.php`
- `tests/Feature/Workflows/LivewireWorkflowConcernTest.php`
- `docs/RUNBOOK.md`
- `docs/agents/workflows.md`

## Manual Test Scenarios

### 1. Registration Page And Legal Gate

- Visit `/register`.
- Confirm the page shows both social registration buttons and the normal email registration form.
- Confirm the social registration section uses a single `terms` checkbox, not separate acceptance fields.
- Attempt social registration without checking terms.
- Files to review:
  - `resources/views/auth/register.blade.php`
  - `app/Http/Controllers/Auth/OauthController.php` - `redirect()`
  - `app/Enums/OauthFlow.php`
  - `routes/web.php`
- Expected result:
  - the request returns to `/register`
  - an inline validation error is shown for terms acceptance

### 2. First-Time Social Signup With Provider Email

- Start from a browser session with no existing local account for the provider email.
- From `/register`, check terms and choose Twitch or Discord.
- Complete the provider round-trip with an account that returns an email address.
- Files to review:
  - `app/Actions/Auth/SocialAuthService.php` - `redirect()`, `callback()`, `resolveEffectiveProviderEmail()`, `login()`
  - `app/Actions/Auth/CompleteSocialRegistration.php` - `complete()`, `recordLegalAcceptance()`
  - `app/Actions/Fortify/CreateNewUser.php` - `createForSocialRegistration()`
  - `resources/views/auth/register.blade.php`
  - `routes/web.php`
  - `tests/Feature/SocialAuthenticationTest.php`
- Expected result:
  - the app redirects to the verification notice, not the dashboard
  - the user is authenticated
  - a new user row is created with `email_verified_at = null`
  - a `provider_auths` row is created for the chosen provider
  - legal acceptance history is stored when terms are enabled
  - no owned team is created during social registration

### 3. Existing Linked Provider Login

- Reuse the account created in scenario 2, or prepare a user that already has a linked `provider_auths` row.
- Visit `/login` and choose the same provider.
- Files to review:
  - `resources/views/auth/login.blade.php`
  - `app/Actions/Auth/SocialAuthService.php` - `callback()`, `updateProviderAuth()`, `providerAuthAttributes()`, `login()`
  - `app/Enums/ExternalAuthProvider.php`
  - `routes/web.php`
  - `tests/Feature/SocialAuthenticationTest.php`
- Expected result:
  - the app redirects to `/dashboard`
  - the existing account is authenticated
  - provider metadata refreshes on the linked `provider_auths` row
  - no duplicate user or provider link is created

### 4. Existing Local Email Must Not Auto-Link

- Ensure a local user already exists for the provider email, but without a linked provider auth.
- Start social registration with a provider account that returns that same email.
- Files to review:
  - `app/Actions/Auth/SocialAuthService.php` - `callback()`, `startOnboardingWorkflow()`
  - `app/Actions/Auth/CompleteSocialRegistration.php` - `emailBelongsToExistingUser()`
  - `app/Workflows/Auth/SocialRegistrationWorkflow.php`
  - `app/Models/WorkflowStore.php`
  - `resources/views/livewire/auth/social-registration-email-form.blade.php`
  - `tests/Feature/SocialAuthenticationTest.php`
- Expected result:
  - the app does not authenticate the user
  - the app does not create a provider link
  - the user is redirected to `/register/social-email`
  - the screen shows the account-match handoff state with a prompt to sign in using the original method first
  - a `workflow_stores` row exists and the workflow is in `existing_account_handoff`

### 5. Missing Provider Email Starts Recovery Workflow

- In a local or debug environment, use the social registration route with `debug=no_email`, or use a provider account that genuinely returns no email.
- Example entry point:
  - `/oauth/twitch/redirect?flow=register&terms=1&debug=no_email`
- Complete the provider round-trip.
- Files to review:
  - `app/Http/Controllers/Auth/OauthController.php` - `redirect()`
  - `app/Actions/Auth/SocialAuthService.php` - `callback()`, `resolveEffectiveProviderEmail()`, `startOnboardingWorkflow()`, `shouldUseDebugEmailOverride()`
  - `app/Workflows/Auth/SocialRegistrationWorkflow.php`
  - `database/migrations/2026_03_22_000003_create_workflow_stores_table.php`
  - `app/Models/WorkflowStore.php`
  - `tests/Feature/SocialAuthenticationTest.php`
- Expected result:
  - the app redirects to `/register/social-email`
  - no user is created yet
  - no provider link is created yet
  - a `workflow_stores` row exists for `App\\Workflows\\Auth\\SocialRegistrationWorkflow`
  - the workflow is in `collect_email`
  - the page shows the provider label, display name, and the local debug warning when applicable

### 6. Complete Missing-Email Recovery With A New Email

- From the `collect_email` state, submit a new valid email address.
- Files to review:
  - `app/Livewire/Auth/SocialRegistrationEmailForm.php` - `mount()`, `submit()`, `workflow()`
  - `app/Actions/Auth/CompleteSocialRegistration.php` - `validateEmail()`, `complete()`
  - `app/Actions/Fortify/CreateNewUser.php` - `createForSocialRegistration()`
  - `app/Workflows/Auth/SocialRegistrationWorkflow.php`
  - `resources/views/livewire/auth/social-registration-email-form.blade.php`
  - `tests/Feature/SocialRegistrationEmailFormTest.php`
- Expected result:
  - the app redirects to the verification notice
  - a new unverified user is created
  - the provider link is created and carries tokens, scopes, and provider identifiers
  - the workflow closes and reaches the `complete` state
  - the session workflow pointer is cleared

### 7. Existing Email During Recovery

- From the `collect_email` state, submit an email that already belongs to a local user.
- Files to review:
  - `app/Livewire/Auth/SocialRegistrationEmailForm.php` - `submit()`, `attemptedEmail()`, `isExistingAccountHandoff()`
  - `app/Actions/Auth/CompleteSocialRegistration.php` - `emailBelongsToExistingUser()`, `validateEmail()`
  - `app/Workflows/Auth/SocialRegistrationWorkflow.php`
  - `resources/views/livewire/auth/social-registration-email-form.blade.php`
  - `tests/Feature/SocialRegistrationEmailFormTest.php`
- Expected result:
  - the form does not proceed
  - no provider link is created
  - the workflow transitions to `existing_account_handoff`
  - the screen shows the attempted email and directs the reviewer back to normal sign-in

### 8. Inline Validation On Recovery Form

- From the `collect_email` state, submit an invalid email such as `not-an-email`.
- Files to review:
  - `app/Livewire/Auth/SocialRegistrationEmailForm.php` - `submit()`
  - `app/Actions/Auth/CompleteSocialRegistration.php` - `validateEmail()`
  - `resources/views/livewire/auth/social-registration-email-form.blade.php`
  - `tests/Feature/SocialRegistrationEmailFormTest.php`
- Expected result:
  - validation renders inline on `fields.email`
  - no user is created
  - the reviewer remains on the same recovery screen

### 9. Missing Workflow Session Fallback

- Open `/register/social-email` in a fresh guest session without first going through the OAuth callback.
- Files to review:
  - `app/Livewire/Auth/SocialRegistrationEmailForm.php` - `mount()`, `workflow()`
  - `app/Workflows/Auth/SocialRegistrationWorkflow.php` - `fromSession()` via `BaseWorkflow`
  - `app/Workflows/BaseWorkflow.php`
  - `routes/web.php`
  - `tests/Feature/SocialRegistrationEmailFormTest.php`
- Expected result:
  - the request redirects back to `/register`
  - the page is not usable without a session-backed workflow store

## Known Limitations And Intentional Behavior

- Only Twitch and Discord are supported social providers in this slice.
- Matching local emails are intentionally blocked from automatic linking in Phase 1.
- The missing-email recovery path is session-backed. If the session is lost, the user is sent back to registration.
- The `debug` override is only available in local or debug-enabled environments.
- Social registration creates an unverified user and sends the reviewer through the normal verification flow.
- Social registration does not create an owned team yet; this is intentional in the current implementation.

## Recommended Supplemental Checks

- Run focused automated coverage alongside manual review:
  - `php artisan test tests/Feature/SocialAuthenticationTest.php`
  - `php artisan test tests/Feature/SocialRegistrationEmailFormTest.php`
  - `php artisan test tests/Feature/Workflows`
- Inspect database state after each scenario rather than relying only on the UI.
- If provider callbacks are difficult to reproduce manually, use the local `debug` override to force the missing-email and existing-email branches while still exercising the real application routes.
