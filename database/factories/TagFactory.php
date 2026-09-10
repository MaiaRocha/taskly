<?php

namespace Database\Factories;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    /**
     * @var list<string>
     */
    private const COLORS = ['#635BFF', '#06B6D4', '#14B8A6', '#EC4899', '#F59E0B', '#22C55E', '#3B82F6'];

    /**
     * Define the model's default state.
     *
     * Deliberately no `normalized_name` key: the Tag model's own mutator on
     * `name` is the single source of truth for that derivation.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->word(),
            'color' => fake()->randomElement(self::COLORS),
        ];
    }
}
