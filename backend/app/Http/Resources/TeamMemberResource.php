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
        ];
    }
}
