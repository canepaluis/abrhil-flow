<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'roles' => $this->roles->map(fn ($role) => ['id' => $role->id, 'slug' => $role->slug, 'name' => $role->name]),
            'responsibilities' => $this->responsibilities ?? [],
            'time_off' => $this->timeOffs->sortByDesc('start_date')->values()->map(fn ($absence) => [
                'id' => $absence->id, 'type' => $absence->type,
                'start_date' => $absence->start_date->toDateString(), 'end_date' => $absence->end_date->toDateString(),
                'is_cancelled' => $absence->is_cancelled,
            ]),
        ];
    }
}
