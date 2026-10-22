<?php

use App\Http\Controllers\Api\CardController;
use App\Http\Controllers\Api\DailyPlanController;
use Illuminate\Support\Facades\Route;

Route::post('/daily-plan', [DailyPlanController::class, 'store']);
Route::apiResource('cards', CardController::class)->only(['index', 'show', 'store']);
Route::put('cards/{card}/workflow', [\App\Http\Controllers\Api\CardWorkflowController::class, 'update']);
Route::get('/card-catalogs', [\App\Http\Controllers\Api\CardCatalogController::class, 'index']);
Route::get('/users', [\App\Http\Controllers\Api\TeamMemberController::class, 'index']);
Route::post('users/{user}/time-off', [\App\Http\Controllers\Api\UserTimeOffController::class, 'store']);
Route::put('users/{user}/time-off/{timeOff}', [\App\Http\Controllers\Api\UserTimeOffController::class, 'update']);
Route::post('users/{user}/time-off/{timeOff}/status', [\App\Http\Controllers\Api\UserTimeOffController::class, 'status']);
Route::get('sprints/current', [\App\Http\Controllers\Api\SprintOverviewController::class, 'current']);
Route::post('sprints/current', [\App\Http\Controllers\Api\SprintOverviewController::class, 'configure']);
Route::get('sprints/history', [\App\Http\Controllers\Api\SprintOverviewController::class, 'history']);
Route::post('sprints/{sprint}/cards/{card}/completion', [\App\Http\Controllers\Api\SprintOverviewController::class, 'completion']);
Route::apiResource('sprints', \App\Http\Controllers\Api\SprintController::class)->only(['index', 'show', 'store', 'update']);
Route::apiResource('sprints', \App\Http\Controllers\Api\SprintController::class)->only(['index', 'show', 'store', 'update']);
