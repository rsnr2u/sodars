<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\LocationController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::prefix('auth')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/profile', [AuthController::class, 'profile']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::prefix('locations')->group(function (): void {
    Route::get('/{resource}', [LocationController::class, 'index'])
        ->whereIn('resource', ['countries', 'states', 'districts', 'cities', 'areas', 'landmarks', 'roads']);

    Route::get('/states-by-country/{parentId}', [LocationController::class, 'children'])
        ->defaults('resource', 'states')
        ->defaults('parentColumn', 'country_id');

    Route::get('/districts-by-state/{parentId}', [LocationController::class, 'children'])
        ->defaults('resource', 'districts')
        ->defaults('parentColumn', 'state_id');

    Route::get('/cities-by-district/{parentId}', [LocationController::class, 'children'])
        ->defaults('resource', 'cities')
        ->defaults('parentColumn', 'district_id');

    Route::get('/areas-by-city/{parentId}', [LocationController::class, 'children'])
        ->defaults('resource', 'areas')
        ->defaults('parentColumn', 'city_id');

    Route::get('/landmarks-by-area/{parentId}', [LocationController::class, 'children'])
        ->defaults('resource', 'landmarks')
        ->defaults('parentColumn', 'area_id');

    Route::get('/roads-by-area/{parentId}', [LocationController::class, 'children'])
        ->defaults('resource', 'roads')
        ->defaults('parentColumn', 'area_id');
});
