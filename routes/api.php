<?php

use App\Http\Controllers\Api\SyncController;
use Illuminate\Support\Facades\Route;

Route::prefix('sync')->group(function () {
    Route::post('receive', [SyncController::class, 'receive']);
    Route::post('push', [SyncController::class, 'push']);
});
