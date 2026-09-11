<?php

use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use App\Support\ColorPalette;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

describe('authentication', function () {
    test('a guest cannot access any tag endpoint', function (string $method, callable $uri) {
        $tag = Tag::factory()->create();

        $this->json($method, $uri($tag))->assertUnauthorized();
    })->with([
        'index' => ['GET', fn () => '/api/tags'],
        'store' => ['POST', fn () => '/api/tags'],
        'update' => ['PATCH', fn (Tag $tag) => "/api/tags/{$tag->id}"],
        'destroy' => ['DELETE', fn (Tag $tag) => "/api/tags/{$tag->id}"],
    ]);
});

describe('route contract', function () {
    test('there is no dedicated show route for a tag', function () {
        $user = User::factory()->create();
        $tag = Tag::factory()->for($user)->create();

        $this->actingAs($user)
            ->getJson("/api/tags/{$tag->id}")
            ->assertMethodNotAllowed();
    });

    test('PUT is not an allowed method for a tag', function () {
        $user = User::factory()->create();
        $tag = Tag::factory()->for($user)->create();

        $this->actingAs($user)
            ->put("/api/tags/{$tag->id}", ['name' => 'Renamed'])
            ->assertMethodNotAllowed();
    });
});

describe('index', function () {
    test('returns only the authenticated user\'s tags, ordered by normalized_name then id', function () {
        $user = User::factory()->create();
        $stranger = User::factory()->create();

        $charlie = Tag::factory()->for($user)->create(['name' => 'Charlie']);
        $alpha = Tag::factory()->for($user)->create(['name' => 'alpha']);
        $bravo = Tag::factory()->for($user)->create(['name' => 'Bravo']);
        Tag::factory()->for($stranger)->create(['name' => 'Alpha']);

        $response = $this->actingAs($user)->getJson('/api/tags');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        $response->assertJsonPath('data.0.id', $alpha->id);
        $response->assertJsonPath('data.1.id', $bravo->id);
        $response->assertJsonPath('data.2.id', $charlie->id);
    });

    test('exposes only the documented fields, wrapped in data, with no pagination metadata', function () {
        $this->travelTo('2026-01-01 12:00:00');
        $user = User::factory()->create();
        $tag = Tag::factory()->for($user)->create([
            'name' => 'Urgente',
            'color' => ColorPalette::AUXILIARY[0],
        ]);

        $response = $this->actingAs($user)->getJson('/api/tags');

        $response->assertOk();
        $response->assertExactJson([
            'data' => [
                [
                    'id' => $tag->id,
                    'name' => 'Urgente',
                    'color' => ColorPalette::AUXILIARY[0],
                    'created_at' => '2026-01-01T12:00:00.000000Z',
                    'updated_at' => '2026-01-01T12:00:00.000000Z',
                ],
            ],
        ]);
    });
});

describe('store', function () {
    test('creates a tag owned by the authenticated user, trimmed and normalized', function () {
        $this->travelTo('2026-01-01 12:00:00');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/tags', [
            'name' => '  Urgente  ',
            'color' => ColorPalette::AUXILIARY[0],
        ]);

        $response->assertCreated();

        $tag = Tag::sole();
        expect($tag->user_id)->toBe($user->id);
        expect($tag->name)->toBe('Urgente');
        expect($tag->normalized_name)->toBe('urgente');

        $response->assertExactJson([
            'data' => [
                'id' => $tag->id,
                'name' => 'Urgente',
                'color' => ColorPalette::AUXILIARY[0],
                'created_at' => '2026-01-01T12:00:00.000000Z',
                'updated_at' => '2026-01-01T12:00:00.000000Z',
            ],
        ]);
    });

    test('ignores a client-supplied user_id and normalized_name', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/tags', [
            'name' => 'Foo',
            'color' => ColorPalette::AUXILIARY[0],
            'user_id' => $otherUser->id,
            'normalized_name' => 'hacked',
        ]);

        $response->assertCreated();

        $tag = Tag::sole();
        expect($tag->user_id)->toBe($user->id);
        expect($tag->normalized_name)->toBe('foo');
    });
});

describe('store validation', function () {
    test('rejects a missing name', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/tags', [
            'color' => ColorPalette::AUXILIARY[0],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('name');
    });

    test('rejects an empty name', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/tags', [
            'name' => '',
            'color' => ColorPalette::AUXILIARY[0],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('name');
    });

    test('rejects a non-string name', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/tags', [
            'name' => ['not', 'a', 'string'],
            'color' => ColorPalette::AUXILIARY[0],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('name');
    });

    test('rejects a name longer than 30 characters', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/tags', [
            'name' => str_repeat('a', 31),
            'color' => ColorPalette::AUXILIARY[0],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('name');
    });

    test('rejects a missing color', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/tags', [
            'name' => 'New tag',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('color');
    });

    test('rejects a null color', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/tags', [
            'name' => 'New tag',
            'color' => null,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('color');
    });

    test('rejects an empty string color', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/tags', [
            'name' => 'New tag',
            'color' => '',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('color');
    });

    test('rejects a color outside the approved palette', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/tags', [
            'name' => 'New tag',
            'color' => '#000000',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('color');
    });

    test('accepts a color from the approved palette', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/tags', [
            'name' => 'New tag',
            'color' => ColorPalette::AUXILIARY[2],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.color', ColorPalette::AUXILIARY[2]);
    });
});

describe('store uniqueness', function () {
    test('rejects a logically duplicate name for the same user, case/whitespace-insensitive', function () {
        $user = User::factory()->create();
        Tag::factory()->for($user)->create(['name' => 'Urgente']);

        $response = $this->actingAs($user)->postJson('/api/tags', [
            'name' => ' urgente ',
            'color' => ColorPalette::AUXILIARY[0],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('name');
        expect(Tag::count())->toBe(1);
    });

    test('allows the same logical name for different users', function () {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        Tag::factory()->for($userA)->create(['name' => 'Urgente']);

        $response = $this->actingAs($userB)->postJson('/api/tags', [
            'name' => ' urgente ',
            'color' => ColorPalette::AUXILIARY[0],
        ]);

        $response->assertCreated();
        expect(Tag::count())->toBe(2);
    });
});

describe('update', function () {
    test('updates only the given field, leaving the rest untouched', function () {
        $user = User::factory()->create();
        $tag = Tag::factory()->for($user)->create([
            'name' => 'Old name',
            'color' => ColorPalette::AUXILIARY[0],
        ]);

        $response = $this->actingAs($user)->patchJson("/api/tags/{$tag->id}", [
            'name' => 'New name',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'New name');
        $response->assertJsonPath('data.color', ColorPalette::AUXILIARY[0]);
        expect($tag->fresh()->normalized_name)->toBe('new name');
    });

    test('updating only color leaves name and normalized_name untouched', function () {
        $user = User::factory()->create();
        $tag = Tag::factory()->for($user)->create([
            'name' => 'Backend',
            'color' => ColorPalette::AUXILIARY[0],
        ]);

        $response = $this->actingAs($user)->patchJson("/api/tags/{$tag->id}", [
            'color' => ColorPalette::AUXILIARY[1],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Backend');
        $response->assertJsonPath('data.color', ColorPalette::AUXILIARY[1]);
        expect($tag->fresh()->normalized_name)->toBe('backend');
    });

    test('an empty payload is a no-op that still returns 200', function () {
        $user = User::factory()->create();
        $tag = Tag::factory()->for($user)->create(['name' => 'Unchanged']);

        $this->actingAs($user)
            ->patchJson("/api/tags/{$tag->id}", [])
            ->assertOk();

        expect($tag->fresh()->name)->toBe('Unchanged');
    });

    test('rejects an empty name when present', function () {
        $user = User::factory()->create();
        $tag = Tag::factory()->for($user)->create();

        $response = $this->actingAs($user)->patchJson("/api/tags/{$tag->id}", [
            'name' => '',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('name');
    });

    test('rejects a non-string name when present', function () {
        $user = User::factory()->create();
        $tag = Tag::factory()->for($user)->create();

        $response = $this->actingAs($user)->patchJson("/api/tags/{$tag->id}", [
            'name' => ['not', 'a', 'string'],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('name');
    });

    test('rejects a name longer than 30 characters when present', function () {
        $user = User::factory()->create();
        $tag = Tag::factory()->for($user)->create();

        $response = $this->actingAs($user)->patchJson("/api/tags/{$tag->id}", [
            'name' => str_repeat('a', 31),
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('name');
    });

    test('rejects a null color when present', function () {
        $user = User::factory()->create();
        $tag = Tag::factory()->for($user)->create();

        $response = $this->actingAs($user)->patchJson("/api/tags/{$tag->id}", [
            'color' => null,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('color');
    });

    test('rejects an empty string color when present', function () {
        $user = User::factory()->create();
        $tag = Tag::factory()->for($user)->create();

        $response = $this->actingAs($user)->patchJson("/api/tags/{$tag->id}", [
            'color' => '',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('color');
    });

    test('rejects a color outside the approved palette when present', function () {
        $user = User::factory()->create();
        $tag = Tag::factory()->for($user)->create();

        $response = $this->actingAs($user)->patchJson("/api/tags/{$tag->id}", [
            'color' => '#000000',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('color');
    });
});

describe('update uniqueness', function () {
    test('rejects renaming a tag to another of the user\'s tags, case/whitespace-insensitive', function () {
        $user = User::factory()->create();
        Tag::factory()->for($user)->create(['name' => 'Urgente']);
        $tagB = Tag::factory()->for($user)->create(['name' => 'Backend']);

        $response = $this->actingAs($user)->patchJson("/api/tags/{$tagB->id}", [
            'name' => ' urgente ',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('name');
        expect($tagB->fresh()->name)->toBe('Backend');
    });

    test('allows re-saving the tag\'s own name with a different case, excluding itself from the check', function () {
        $user = User::factory()->create();
        $tag = Tag::factory()->for($user)->create(['name' => 'Urgente']);

        $response = $this->actingAs($user)->patchJson("/api/tags/{$tag->id}", [
            'name' => ' URGENTE ',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'URGENTE');
        expect($tag->fresh()->normalized_name)->toBe('urgente');
    });
});

describe('update ownership', function () {
    test('a stranger cannot update another user\'s tag', function () {
        $stranger = User::factory()->create();
        $tag = Tag::factory()->create(['name' => 'Original name']);

        $this->actingAs($stranger)
            ->patchJson("/api/tags/{$tag->id}", ['name' => 'Hacked'])
            ->assertForbidden();

        expect($tag->fresh()->name)->toBe('Original name');
    });

    test('a missing tag returns 404', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/api/tags/999999', ['name' => 'Whatever'])
            ->assertNotFound();
    });
});

describe('destroy', function () {
    test('the owner can delete their tag', function () {
        $user = User::factory()->create();
        $tag = Tag::factory()->for($user)->create();

        $response = $this->actingAs($user)->deleteJson("/api/tags/{$tag->id}");

        $response->assertNoContent();
        expect($response->getContent())->toBe('');
        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
    });

    test('deleting a tag does not delete tasks associated with it', function () {
        $user = User::factory()->create();
        $tag = Tag::factory()->for($user)->create();
        $task = Task::factory()->create();
        $task->tags()->attach($tag);

        $this->actingAs($user)->deleteJson("/api/tags/{$tag->id}")->assertNoContent();

        $this->assertModelExists($task);
    });

    test('a stranger cannot delete another user\'s tag', function () {
        $stranger = User::factory()->create();
        $tag = Tag::factory()->create();

        $this->actingAs($stranger)
            ->deleteJson("/api/tags/{$tag->id}")
            ->assertForbidden();

        $this->assertModelExists($tag);
    });

    test('a missing tag returns 404', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->deleteJson('/api/tags/999999')->assertNotFound();
    });
});
