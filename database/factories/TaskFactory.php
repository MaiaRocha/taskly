<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => ucfirst(fake()->sentence(4)),
            'short_description' => fake()->optional()->sentence(),
            'description' => fake()->optional()->paragraph(),
            'status' => TaskStatus::NotStarted,
            'due_at' => fake()->optional()->dateTimeBetween('now', '+2 weeks'),
            'position' => 0,
        ];
    }

    /**
     * Marks the task as completed. completed_at is intentionally left unset
     * here — the Task model's own saving hook derives it, so the factory
     * never duplicates that rule.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TaskStatus::Completed,
        ]);
    }
}
