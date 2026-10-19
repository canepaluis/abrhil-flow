<?php

use App\Http\Controllers\Api\CardController;
use App\Http\Controllers\Api\DailyPlanController;
use Illuminate\Support\Facades\Route;

Route::post('/daily-plan', [DailyPlanController::class, 'store']);
Route::apiResource('cards', CardController::class)->only(['index', 'show', 'store']);
Route::get('/card-catalogs', [\App\Http\Controllers\Api\CardCatalogController::class, 'index']);
Route::apiResource('sprints', \App\Http\Controllers\Api\SprintController::class)->only(['index', 'show', 'store', 'update']);
Route::apiResource('sprints', \App\Http\Controllers\Api\SprintController::class)->only(['index', 'show', 'store', 'update']);
