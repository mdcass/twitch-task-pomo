<?php

use App\Http\Controllers\LocalWidgetPreviewController;
use Illuminate\Support\Facades\Route;

Route::prefix('local/widgets')->name('local.widgets.')->group(function (): void {
    Route::get('/', [LocalWidgetPreviewController::class, 'index'])
        ->name('index');

    Route::get('/task-list', [LocalWidgetPreviewController::class, 'taskList'])
        ->name('task-list');

    Route::get('/pomodoro', [LocalWidgetPreviewController::class, 'pomodoro'])
        ->name('pomodoro');
});
