<?php

namespace Database\Factories;

use App\Models\Tag;
use App\Models\User;
use App\Support\ColorPalette;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
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
            'color' => fake()->randomElement(ColorPalette::AUXILIARY),
        ];
    }
}
