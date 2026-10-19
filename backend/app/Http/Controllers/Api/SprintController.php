<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveSprintRequest;
use App\Http\Resources\SprintResource;
use App\Models\Sprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SprintController extends Controller
{
    public function index()
    {
        return SprintResource::collection(Sprint::with(['cards.assignedTo.timeOffs', 'cards.status', 'cards.cardType', 'participants.timeOffs', 'participants.roles', 'cards.assignedTo.roles'])->orderByDesc('start_date')->orderByDesc('id')->get());
    }

    public function show(Sprint $sprint)
    {
        return new SprintResource($sprint->load(['cards.assignedTo.timeOffs', 'cards.status', 'cards.cardType', 'participants.timeOffs', 'participants.roles', 'cards.assignedTo.roles']));
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
            // Serialize plan writes, including when no sprints exist yet.
            // This shares the singleton lock used by current-sprint configuration.
            DB::table('sprint_settings')->where('id', 1)->lockForUpdate()->first();
            $conflict = Sprint::whereDate('start_date', '<=', $data['end_date'])
                ->whereDate('end_date', '>=', $data['start_date'])
                ->when($sprint->exists, fn ($query) => $query->where('id', '!=', $sprint->id))
                ->orderBy('start_date')->first();
            if ($conflict) {
                $message = 'El periodo se cruza con el sprint "'.$conflict->name.'" ('
                    .$conflict->start_date->toDateString().' al '.$conflict->end_date->toDateString()
                    .'). Las fechas de los sprints no pueden compartir ningún día.';
                throw ValidationException::withMessages(['start_date' => $message, 'end_date' => $message]);
            }
            $cards = $data['cards'];
            $participants = $data['participant_ids'];
            unset($data['cards'], $data['participant_ids']);
            $data['members_count'] = count($participants);
            $data['working_days'] = array_map('intval', $data['working_days']);
            $sprint->fill($data)->save();
            $sprint->participants()->sync($participants);
            $pivot = [];
            foreach ($cards as $card) {
                $pivot[$card['id']] = array_intersect_key($card, array_flip(['start_date', 'end_date', 'estimated_minutes', 'is_completed']));
            }
            $sprint->cards()->sync($pivot);
        });

        return new SprintResource($sprint->load(['cards.assignedTo.timeOffs', 'cards.status', 'cards.cardType', 'participants.timeOffs', 'participants.roles', 'cards.assignedTo.roles']));
    }
}
