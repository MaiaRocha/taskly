<?php

namespace App\Actions;

use App\Enums\TaskActivityType;
use App\Models\Task;
use App\Models\TaskActivity;

class RecordTaskActivity
{
    /**
     * Persists one Activity for the given Task. This Action never decides
     * WHETHER something changed (status/due_at diffing, which Tags were
     * added/removed, which Attachments were involved) — that decision
     * belongs to the caller, who already has the before/after values at
     * hand. This only records an event the caller has already determined
     * is real.
     *
     * @param  array<string, mixed>  $data
     */
    public function __invoke(Task $task, TaskActivityType $type, array $data = []): TaskActivity
    {
        return $task->activities()->create([
            'type' => $type,
            'data' => $data,
        ]);
    }
}
