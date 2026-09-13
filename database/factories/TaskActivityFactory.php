<?php

namespace Database\Factories;

use App\Enums\TaskActivityType;
use App\Models\Task;
use App\Models\TaskActivity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskActivity>
 */
class TaskActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * A plain, valid `task_created` event by default — the simplest
     * Activity shape (empty `data`), since most tests care about the
     * envelope (task_id/type/created_at/cascade), not a specific payload.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'type' => TaskActivityType::TaskCreated,
            'data' => [],
        ];
    }

    public function statusChanged(string $from = 'not_started', string $to = 'in_progress'): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => TaskActivityType::StatusChanged,
            'data' => ['from' => $from, 'to' => $to],
        ]);
    }
}
