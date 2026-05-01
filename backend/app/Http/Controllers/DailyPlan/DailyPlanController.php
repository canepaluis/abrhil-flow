<?php

namespace App\Http\Controllers\DailyPlan;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDailyPlanRequest;
use App\Models\DailyPlan;

class DailyPlanController extends Controller
{
    public function store (StoreDailyPlanRequest $request) {
        $validated_data = $request->validated();

        $daily = DailyPlan::create($validated_data);

        return response()->json([
            'id' => $daily->id,
            'date' => $daily->date,
            'activity_type_id' => $daily->activity_type_id,
            'assigned_to' => $daily->assigned_to,
            'card_id' => $daily->card_id,
            'estimated_minutes' => $daily->estimated_minutes,
            'real_minutes' => $daily->real_minutes,
        ], 201);
    }
}
