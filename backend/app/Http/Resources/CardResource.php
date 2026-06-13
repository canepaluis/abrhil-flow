<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'jira_link' => $this->jira_link,
            'priority' => $this->priority,
            'card_type' => [
                'name' => $this->cardType?->name,
                'description' => $this->cardType?->description
            ],
            'assigned_to' => [
                'name' => $this->assignedTo?->name,
                'email' => $this->assignedTo?->email
            ],
            'module' => [
                'name' => $this->module?->name
            ],
            'project' => [
                'name' => $this->project?->name,
                'description' => $this->project?->description
            ],
            'team' => [
                'name' => $this->team?->name
            ],
            'platform' => [
                'name' => $this->platform?->name
            ],
            'status' => [
                'name' => $this->status?->name
            ],
            'creator' => [
                'name' => $this->creator?->name,
                'email' => $this->creator?->email
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}