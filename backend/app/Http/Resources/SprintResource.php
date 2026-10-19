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

        return [
            'id' => $this->id,
            'name' => $this->name,
            'goal' => $this->goal,
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
            'completed_cards' => $this->cards->filter(fn ($card) => (bool) $card->pivot->is_completed)->count(),
            'completed_minutes' => (int) $this->cards->filter(fn ($card) => (bool) $card->pivot->is_completed)->sum('pivot.estimated_minutes'),
            'cards' => $this->cards->map(fn ($card) => [
                'id' => $card->id,
                'title' => $card->title,
                'description' => $card->description,
                'priority' => $card->priority,
                'jira_link' => $card->jira_link,
                'card_type' => $card->cardType?->name,
                'is_completed' => (bool) $card->pivot->is_completed,
                'assigned_to' => $card->assignedTo?->name,
                'assigned_to_id' => $card->assigned_to,
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
