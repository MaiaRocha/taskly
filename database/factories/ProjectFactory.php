<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * A small, valid palette — not an exhaustive list, just enough variety for tests.
     *
     * @var list<string>
     */
    private const COLORS = ['#635BFF', '#06B6D4', '#14B8A6', '#EC4899', '#F59E0B', '#22C55E', '#3B82F6'];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => ucfirst(fake()->words(3, true)),
            'description' => fake()->optional()->paragraph(),
            'color' => fake()->randomElement(self::COLORS),
            'position' => 0,
        ];
    }
}
