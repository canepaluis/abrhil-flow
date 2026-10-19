<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SprintResource;
use App\Models\Sprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SprintOverviewController extends Controller
{
    private function today(): string
    {
        return now(config('sprints.timezone'))->toDateString();
    }

    public function current()
    {
        $today = $this->today();
        $manualId = DB::table('sprint_settings')->where('id', 1)->value('current_sprint_id');
        $sprint = $manualId ? Sprint::find($manualId) : Sprint::orderByDesc('id')->first();
        if (!$manualId && $sprint && ($sprint->start_date->toDateString() > $today || $sprint->end_date->toDateString() < $today)) {
            $sprint = null;
        }
        return response()->json([
            'data' => $sprint ? (new SprintResource($sprint->load(['cards.assignedTo.timeOffs', 'cards.status', 'cards.cardType', 'participants.timeOffs', 'participants.roles', 'cards.assignedTo.roles'])))->resolve() : null,
            'mode' => $manualId ? 'manual' : 'automatic',
            'today' => $today,
            'timezone' => config('sprints.timezone'),
        ]);
    }

    public function configure(Request $request)
    {
        $data = $request->validate(['sprint_id' => 'present|nullable|integer|exists:sprints,id']);
        DB::transaction(function () use ($data) {
            DB::table('sprint_settings')->where('id', 1)->lockForUpdate()->first();
            DB::table('sprint_settings')->where('id', 1)->update(['current_sprint_id' => $data['sprint_id']]);
        });
        return $this->current();
    }

    public function history()
    {
        return SprintResource::collection(Sprint::with(['cards.assignedTo.timeOffs', 'cards.status', 'cards.cardType', 'participants.timeOffs', 'participants.roles', 'cards.assignedTo.roles'])
            ->where('end_date', '<', $this->today())->orderByDesc('end_date')->orderByDesc('id')->get());
    }

    public function completion(Request $request, Sprint $sprint, int $card)
    {
        $data = $request->validate(['is_completed' => 'required|boolean']);
        abort_unless($sprint->cards()->where('cards.id', $card)->exists(), 404);
        $sprint->cards()->updateExistingPivot($card, ['is_completed' => $data['is_completed']]);
        return new SprintResource($sprint->load(['cards.assignedTo.timeOffs', 'cards.status', 'cards.cardType', 'participants.timeOffs', 'participants.roles', 'cards.assignedTo.roles']));
    }
}
