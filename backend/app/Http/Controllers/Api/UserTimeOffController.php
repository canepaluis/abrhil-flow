<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveTimeOffRequest;
use App\Models\User;
use App\Models\UserTimeOff;
use Illuminate\Http\Request;

class UserTimeOffController extends Controller
{
    public function store(SaveTimeOffRequest $request, User $user)
    {
        $absence = $user->timeOffs()->create($request->validated());
        return response()->json(['data' => $absence->refresh()], 201);
    }

    public function update(SaveTimeOffRequest $request, User $user, UserTimeOff $timeOff)
    {
        abort_unless($timeOff->user_id === $user->id, 404);
        $timeOff->update($request->validated());
        return response()->json(['data' => $timeOff]);
    }

    public function status(Request $request, User $user, UserTimeOff $timeOff)
    {
        abort_unless($timeOff->user_id === $user->id, 404);
        $data = $request->validate(['is_cancelled' => 'required|boolean']);
        $timeOff->update($data);
        return response()->json(['data' => $timeOff]);
    }
}
