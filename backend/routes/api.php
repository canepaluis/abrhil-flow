<?php

use App\Http\Controllers\Api\CardController;
use App\Http\Controllers\Api\DailyPlanController;
use Illuminate\Support\Facades\Route;

Route::post('/daily-plan', [DailyPlanController::class, 'store']);
Route::apiResource('cards', CardController::class)->only(['index', 'show', 'store']);