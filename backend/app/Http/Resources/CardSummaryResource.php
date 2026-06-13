<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CardSummaryResource extends JsonResource
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
            'jira_link' => $this->jira_link,
            'priority' => $this->priority,
            'assigned_to' => [
                'name' => $this->assignedTo?->name,
                'email' => $this->assignedTo?->email
            ],
            'card_type' => [
                'name' => $this->cardType?->name,
                'description' => $this->cardType?->description
            ],
            'status' => [
                'name' => $this->status?->name
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}