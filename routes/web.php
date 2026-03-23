<?php

use App\Http\Controllers\Auth\OauthController;
use App\Livewire\Auth\SocialRegistrationEmailForm;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'));

Route::view('/terms', 'terms')->name('terms.show');
Route::view('/policy', 'policy')->name('policy.show');

Route::prefix('oauth')->name('oauth.')->middleware('guest')->group(function (): void {
    Route::get('{provider}/redirect', [OauthController::class, 'redirect'])
        ->name('redirect');
    Route::get('callback/{provider}', [OauthController::class, 'callback'])
        ->name('callback');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/register/social-email', SocialRegistrationEmailForm::class)
        ->name('register.social-email');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function (): void {
    Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard');
});
