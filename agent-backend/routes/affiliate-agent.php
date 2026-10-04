<?php

use App\Http\Controllers\Api\Admin\Agent\ContentController;
use App\Http\Controllers\Api\Admin\Agent\PreferenceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/admin/affiliate-agent')->middleware(['auth:sanctum', 'admin', 'throttle:api'])->group(function () {
    Route::get('overview', [ContentController::class, 'overview']);
    Route::get('media', [ContentController::class, 'media']);
    Route::post('media', [ContentController::class, 'upload']);
    Route::get('contents', [ContentController::class, 'index']);
    Route::post('contents', [ContentController::class, 'store']);
    Route::get('contents/{content}', [ContentController::class, 'show']);
    Route::post('contents/{content}/revisions', [ContentController::class, 'revision']);
    Route::put('contents/{content}/locks', [ContentController::class, 'locks']);
    Route::post('contents/{content}/approve', [ContentController::class, 'approve']);
    Route::post('contents/{content}/reject', [ContentController::class, 'reject']);
    Route::post('contents/{content}/restore', [ContentController::class, 'restore']);
    Route::post('contents/{content}/publications', [ContentController::class, 'schedule']);
    Route::get('contents/{content}/export', [ContentController::class, 'export']);
    Route::post('publications/{publication}/confirm', [ContentController::class, 'confirm']);
    Route::apiResource('preferences', PreferenceController::class)->except('show');
});
Route::get('v1/affiliate-agent/assets/{version}/{kind}', [ContentController::class, 'asset'])->middleware('signed')->name('agent.asset');
