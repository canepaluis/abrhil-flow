<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{CardType, Module, Platform, Project, Status, Team, User};

class CardCatalogController extends Controller
{
    public function index()
    {
        return response()->json(['data' => [
            'card_types' => CardType::orderBy('name')->get(['id', 'name']),
            'modules' => Module::orderBy('name')->get(['id', 'name']),
            'platforms' => Platform::orderBy('name')->get(['id', 'name']),
            'projects' => Project::whereNull('deleted_at')->orderBy('name')->get(['id', 'name', 'team_id']),
            'statuses' => Status::orderBy('order')->get(['id', 'name']),
            'teams' => Team::orderBy('name')->get(['id', 'name']),
            'users' => User::orderBy('name')->get(['id', 'name']),
        ]]);
    }
}
