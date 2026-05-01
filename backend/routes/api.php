<?php

use App\Http\Controllers\DailyPlan\DailyPlanController;
use Illuminate\Support\Facades\Route;

Route::post('/daily-plan', [DailyPlanController::class, 'store']);