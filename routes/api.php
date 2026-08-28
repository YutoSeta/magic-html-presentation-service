<?php

use App\Http\Controllers\Api\V1\FontPolicyController;
use App\Http\Controllers\Api\V1\PresentationController;
use App\Http\Controllers\CapabilityController;
use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/', CapabilityController::class);
Route::get('/__verify', [CapabilityController::class, 'verify']);
Route::get('/health', HealthController::class)->name('health');
Route::middleware(['service', 'throttle:presentation-requests'])
    ->group(function (): void {
        Route::post('/v1/presentations/materialize', [PresentationController::class, 'store']);
        Route::post('/v1/presentations/font-assets/check', FontPolicyController::class);
    });
