<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * No physical file is written — only plausible metadata. Upload/storage
     * is Phase 7's concern.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $extension = fake()->randomElement(['jpg', 'png', 'pdf', 'docx']);

        return [
            'task_id' => Task::factory(),
            'original_name' => fake()->slug(3).'.'.$extension,
            'path' => 'attachments/'.fake()->uuid().'.'.$extension,
            'mime_type' => match ($extension) {
                'jpg' => 'image/jpeg',
                'png' => 'image/png',
                'pdf' => 'application/pdf',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            },
            'size' => fake()->numberBetween(1_000, 4_000_000),
        ];
    }
}
