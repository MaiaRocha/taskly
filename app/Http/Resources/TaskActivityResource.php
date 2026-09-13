<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskActivityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * task_id/updated_at are deliberately not exposed — the frontend always
     * reads this nested under a specific Task's own timeline, and an
     * Activity never has an updated_at (see TaskActivity::UPDATED_AT).
     * `type` is the raw enum value; the frontend owns all label translation
     * (e.g. "task_created" -> "Tarefa criada"), never the backend.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'data' => $this->data,
            // Explicitly normalized to UTC ISO-8601 with a literal `Z`
            // (e.g. "2026-09-15T21:00:00Z"), matching the exact format
            // already used for every date snapshot inside `data` itself
            // (see due_at_changed) — one consistent format across the
            // whole Activity payload, not the framework's default
            // fractional-second JSON serialization.
            'created_at' => $this->created_at->toIso8601ZuluString(),
        ];
    }
}
