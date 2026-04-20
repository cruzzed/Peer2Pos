<?php

use App\Http\Controllers\Api\JoinController;
use App\Http\Controllers\Api\SyncController;
use Illuminate\Support\Facades\Route;

// Join endpoint: token validated in request body — no Bearer required for first contact
Route::post('sync/join', [JoinController::class, 'join']);

// All other sync endpoints require a valid workgroup Bearer token
Route::prefix('sync')->middleware('workgroup')->group(function () {
    Route::post('receive', [SyncController::class, 'receive']);
    Route::post('push', [SyncController::class, 'push']);
    Route::get('snapshot', [SyncController::class, 'snapshot']);
});
