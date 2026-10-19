<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SprintResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dates = $this->workingDates();
        $capacity = count($dates) * $this->daily_minutes * $this->members_count;
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
            'estimated_minutes' => $estimated,
            'remaining_minutes' => $capacity - $estimated,
            'cards' => $this->cards->map(fn ($card) => [
                'id' => $card->id,
                'title' => $card->title,
                'assigned_to' => $card->assignedTo?->name,
                'status' => $card->status?->name,
                'archived' => $card->trashed(),
                'start_date' => $card->pivot->start_date,
                'end_date' => $card->pivot->end_date,
                'estimated_minutes' => (int) $card->pivot->estimated_minutes,
            ]),
        ];
    }
}
