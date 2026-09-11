<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
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
            'project_id' => $this->project_id,
            'title' => $this->title,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'status' => $this->status->value,
            'due_at' => $this->due_at,
            'position' => $this->position,
            'completed_at' => $this->completed_at,
            'overdue' => $this->overdue,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
