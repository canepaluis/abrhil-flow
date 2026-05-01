<?php

namespace App\Http\Controllers\DailyPlan;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDailyPlanRequest;
use App\Models\DailyPlan;
use Illuminate\Http\Request;

class DailyPlanController extends Controller
{
    public function store (StoreDailyPlanRequest $request) {
        $validated_data = $request->validated();

        $daily = DailyPlan::create($validated_data);

        return response()->json($daily, 201);
    }
}
