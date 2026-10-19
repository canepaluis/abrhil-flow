<?php

use App\Http\Controllers\Api\CardController;
use App\Http\Controllers\Api\DailyPlanController;
use Illuminate\Support\Facades\Route;

Route::post('/daily-plan', [DailyPlanController::class, 'store']);
Route::apiResource('cards', CardController::class)->only(['index', 'show', 'store']);
Route::get('/card-catalogs', [\App\Http\Controllers\Api\CardCatalogController::class, 'index']);
Route::get('sprints/current', [\App\Http\Controllers\Api\SprintOverviewController::class, 'current']);
Route::post('sprints/current', [\App\Http\Controllers\Api\SprintOverviewController::class, 'configure']);
Route::get('sprints/history', [\App\Http\Controllers\Api\SprintOverviewController::class, 'history']);
Route::post('sprints/{sprint}/cards/{card}/completion', [\App\Http\Controllers\Api\SprintOverviewController::class, 'completion']);
Route::apiResource('sprints', \App\Http\Controllers\Api\SprintController::class)->only(['index', 'show', 'store', 'update']);
Route::apiResource('sprints', \App\Http\Controllers\Api\SprintController::class)->only(['index', 'show', 'store', 'update']);
