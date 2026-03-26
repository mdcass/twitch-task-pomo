<?php

use App\Http\Controllers\CanvasController;
use App\Http\Controllers\IntegrationConnectionController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\OverlayCanvasController;
use App\Http\Controllers\OverlayWidgetController;
use App\Http\Controllers\WidgetController;
use App\Http\Controllers\Auth\OauthController;
use App\Http\Middleware\AllowAppOriginFrameEmbedding;
use App\Http\Middleware\DisableDebugbar;
use App\Http\Middleware\DenyFrameEmbedding;
use App\Livewire\Auth\SocialRegistrationEmailForm;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Support\Facades\Route;

Route::middleware(DenyFrameEmbedding::class)->group(function (): void {
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
        Route::resource('canvases', CanvasController::class)->only(['index', 'edit']);
        Route::resource('widgets', WidgetController::class)->only(['index', 'edit']);
        Route::get('/integrations', [IntegrationController::class, 'index'])->name('integrations.index');
        Route::get('/integrations/connect/{provider}', [IntegrationConnectionController::class, 'redirect'])->name('integrations.redirect');
        Route::get('/integrations/callback/{provider}', [IntegrationConnectionController::class, 'callback'])->name('integrations.callback');
    });
});

Route::name('overlay.')
    ->middleware([ValidateSignature::relative(), DenyFrameEmbedding::class])
    ->group(function (): void {
        Route::get('/overlay/{canvasUuid}', [OverlayCanvasController::class, 'show'])
            ->name('canvases.show');
        Route::get('/overlay/published-widgets/{widget:uuid}/{key}', [OverlayWidgetController::class, 'showPublished'])
            ->name('widgets.published');
    });

Route::name('overlay.')
    ->middleware([ValidateSignature::relative(), DisableDebugbar::class, AllowAppOriginFrameEmbedding::class])
    ->group(function (): void {
        Route::get('/overlay/widgets/{widgetInstance}', [OverlayWidgetController::class, 'show'])
            ->name('widgets.show');
    });
