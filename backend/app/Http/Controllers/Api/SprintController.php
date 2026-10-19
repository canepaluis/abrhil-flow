<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveSprintRequest;
use App\Http\Resources\SprintResource;
use App\Models\Sprint;
use Illuminate\Support\Facades\DB;

class SprintController extends Controller
{
    public function index()
    {
        return SprintResource::collection(Sprint::with(['cards.assignedTo', 'cards.status'])->orderByDesc('start_date')->orderByDesc('id')->get());
    }

    public function show(Sprint $sprint)
    {
        return new SprintResource($sprint->load(['cards.assignedTo', 'cards.status']));
    }

    public function store(SaveSprintRequest $request)
    {
        return $this->save($request, new Sprint)->response()->setStatusCode(201);
    }

    public function update(SaveSprintRequest $request, Sprint $sprint)
    {
        return $this->save($request, $sprint);
    }

    private function save(SaveSprintRequest $request, Sprint $sprint): SprintResource
    {
        DB::transaction(function () use ($request, $sprint) {
            $data = $request->validated();
            $cards = $data['cards'];
            unset($data['cards']);
            $data['working_days'] = array_map('intval', $data['working_days']);
            $sprint->fill($data)->save();
            $pivot = [];
            foreach ($cards as $card) {
                $pivot[$card['id']] = array_intersect_key($card, array_flip(['start_date', 'end_date', 'estimated_minutes']));
            }
            $sprint->cards()->sync($pivot);
        });

        return new SprintResource($sprint->load(['cards.assignedTo', 'cards.status']));
    }
}
