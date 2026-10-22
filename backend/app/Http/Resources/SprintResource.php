<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Services\SprintAvailability;

class SprintResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dates = $this->workingDates();
        $availability = app(SprintAvailability::class)->calculate($this->resource);
        $capacity = $availability['capacity_minutes'];
        $estimated = (int) $this->cards->sum('pivot.estimated_minutes');
        $completed = $this->cards->filter(function ($card) {
            if ($this->production_release_date) return (bool) $card->pivot->is_completed;
            if ($this->review_date || $this->dev_review_date || $this->resource->readyForClosure() || $this->end_date->toDateString() >= now(config('sprints.timezone'))->toDateString()) {
                return $card->workflow_state === 'testing_approved';
            }
            // Preserve imported history; recorded workflow events take precedence.
            if ($card->workflowEvents->isEmpty()) return (bool) $card->pivot->is_completed;
            $cutoff = \Carbon\CarbonImmutable::parse($this->end_date->toDateString(), config('sprints.timezone'))->endOfDay();
            $event = $card->workflowEvents->filter(fn ($event) => $event->created_at->lte($cutoff))->first();
            return $event ? $event->to_state === 'testing_approved' : (bool) $card->pivot->is_completed;
        });

        return [
            'id' => $this->id,
            'name' => $this->name,
            'goal' => $this->goal,
            'closure' => [
                'ready' => $this->resource->readyForClosure(),
                'status' => $this->production_release_date ? 'finalized' : ($this->resource->readyForClosure() || $this->review_date || $this->dev_review_date ? 'pending_release' : 'in_progress'),
                'review_date' => $this->review_date?->toDateString(),
                'dev_review_date' => $this->dev_review_date?->toDateString(),
                'dev_review_jira_url' => $this->dev_review_jira_url,
                'production_release_date' => $this->production_release_date?->toDateString(),
            ],
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date->toDateString(),
            'members_count' => $this->members_count,
            'daily_minutes' => $this->daily_minutes,
            'working_days' => $this->working_days,
            'working_dates' => $dates,
            'capacity_minutes' => $capacity,
            'developer_count' => $availability['developer_count'],
            'development_estimated_minutes' => $availability['development_estimated_minutes'],
            'other_estimated_minutes' => $availability['other_estimated_minutes'],
            'unclassified_estimated_minutes' => $availability['unclassified_estimated_minutes'],
            'gross_capacity_minutes' => $availability['gross_capacity_minutes'],
            'absence_minutes' => $availability['absence_minutes'],
            'requires_participants' => $availability['requires_participants'],
            'participants' => $availability['participants'],
            'planning_warnings' => $availability['planning_warnings'],
            'estimated_minutes' => $estimated,
            'remaining_minutes' => $capacity - $availability['development_estimated_minutes'],
            'completed_cards' => $completed->count(),
            'completed_minutes' => (int) $completed->sum('pivot.estimated_minutes'),
            'cards' => $this->cards->map(fn ($card) => [
                'id' => $card->id,
                'workflow' => app(\App\Services\CardWorkflow::class)->describe($card),
                'development_owner_id' => $card->developer_id ?? $card->assigned_to,
                'title' => $card->title,
                'description' => $card->description,
                'priority' => $card->priority,
                'jira_link' => $card->jira_link,
                'card_type' => $card->cardType?->name,
                'is_completed' => $completed->contains('id', $card->id),
                'assigned_to' => $card->workflowOwner()?->name,
                'assigned_to_id' => $card->workflowOwner()?->id,
                'status' => $card->status?->name,
                'archived' => $card->trashed(),
                'start_date' => $card->pivot->start_date,
                'end_date' => $card->pivot->end_date,
                'estimated_minutes' => (int) $card->pivot->estimated_minutes,
                ...$availability['card_availability'][$card->id],
            ]),
        ];
    }
}
