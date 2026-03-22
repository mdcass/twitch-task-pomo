<?php

use App\Http\Controllers\Auth\OauthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::view('/terms', 'terms')->name('terms.show');
Route::view('/policy', 'policy')->name('policy.show');

Route::prefix('oauth')->name('oauth.')->middleware('guest')->group(function () {
    Route::get('{provider}/redirect', [OauthController::class, 'redirect'])
        ->name('redirect');
    Route::get('callback/{provider}', [OauthController::class, 'callback'])
        ->name('callback');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
});
