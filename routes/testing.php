<?php

use App\Http\Controllers\Testing\TestingSocialAuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('testing/oauth')->name('testing.oauth.')->group(function (): void {
    Route::get('{provider}/scenarios/{scenario}', [TestingSocialAuthController::class, 'storeScenario'])
        ->name('scenario');

    Route::get('{provider}/authorize', [TestingSocialAuthController::class, 'authorize'])
        ->name('authorize');
});
