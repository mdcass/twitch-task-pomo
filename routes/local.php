<?php

use App\Http\Controllers\LocalWidgetPreviewController;
use App\Http\Controllers\LocalWidgetSpotifyController;
use Illuminate\Support\Facades\Route;

Route::prefix('local/widgets')->name('local.widgets.')->group(function (): void {
    Route::middleware([
        'auth:sanctum',
        config('jetstream.auth_session'),
    ])->group(function (): void {
        Route::get('/', [LocalWidgetPreviewController::class, 'index'])
            ->name('index');

        Route::get('/spotify/connect', [LocalWidgetSpotifyController::class, 'connect'])
            ->name('spotify.connect');

        Route::post('/spotify/disconnect', [LocalWidgetSpotifyController::class, 'disconnect'])
            ->name('spotify.disconnect');
    });

    Route::get('/task-list', [LocalWidgetPreviewController::class, 'taskList'])
        ->name('task-list');

    Route::get('/pomodoro', [LocalWidgetPreviewController::class, 'pomodoro'])
        ->name('pomodoro');

    Route::get('/spotify', [LocalWidgetSpotifyController::class, 'show'])
        ->name('spotify.show');

    Route::get('/spotify/callback', [LocalWidgetSpotifyController::class, 'callback'])
        ->name('spotify.callback');
});
