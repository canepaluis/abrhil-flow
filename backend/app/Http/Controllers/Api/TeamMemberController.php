<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TeamMemberResource;
use App\Models\User;

class TeamMemberController extends Controller
{
    public function index()
    {
        return TeamMemberResource::collection(User::with(['roles', 'timeOffs'])->orderBy('name')->get());
    }
}
