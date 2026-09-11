<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

describe('authentication', function () {
    test('a guest cannot access any project endpoint', function (string $method, callable $uri) {
        $project = Project::factory()->create();

        $this->json($method, $uri($project))->assertUnauthorized();
    })->with([
        'index' => ['GET', fn () => '/api/projects'],
        'store' => ['POST', fn () => '/api/projects'],
        'show' => ['GET', fn (Project $project) => "/api/projects/{$project->id}"],
        'update' => ['PATCH', fn (Project $project) => "/api/projects/{$project->id}"],
        'destroy' => ['DELETE', fn (Project $project) => "/api/projects/{$project->id}"],
    ]);
});

describe('route contract', function () {
    test('PUT is not an allowed method for a project', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)
            ->put("/api/projects/{$project->id}", ['name' => 'Renamed'])
            ->assertMethodNotAllowed();
    });
});

describe('index', function () {
    test('returns only the authenticated user\'s projects, ordered by position then id', function () {
        $user = User::factory()->create();
        $stranger = User::factory()->create();

        $second = Project::factory()->for($user)->create(['position' => 1]);
        $first = Project::factory()->for($user)->create(['position' => 0]);
        Project::factory()->for($stranger)->create(['position' => 0]);

        $response = $this->actingAs($user)->getJson('/api/projects');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.id', $first->id);
        $response->assertJsonPath('data.1.id', $second->id);
    });

    test('breaks a position tie by id ascending', function () {
        $user = User::factory()->create();
        $olderTiedProject = Project::factory()->for($user)->create(['position' => 5]);
        $newerTiedProject = Project::factory()->for($user)->create(['position' => 5]);

        $response = $this->actingAs($user)->getJson('/api/projects');

        $response->assertJsonPath('data.0.id', $olderTiedProject->id);
        $response->assertJsonPath('data.1.id', $newerTiedProject->id);
    });

    test('exposes only the documented fields, wrapped in data, with no pagination metadata', function () {
        $this->travelTo('2026-01-01 12:00:00');
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create([
            'name' => 'Website Redesign',
            'description' => 'Revamp the marketing site',
            'color' => Project::COLORS[0],
            'position' => 0,
        ]);

        $response = $this->actingAs($user)->getJson('/api/projects');

        $response->assertOk();
        $response->assertExactJson([
            'data' => [
                [
                    'id' => $project->id,
                    'name' => 'Website Redesign',
                    'description' => 'Revamp the marketing site',
                    'color' => Project::COLORS[0],
                    'position' => 0,
                    'created_at' => '2026-01-01T12:00:00.000000Z',
                    'updated_at' => '2026-01-01T12:00:00.000000Z',
                ],
            ],
        ]);
    });
});

describe('store', function () {
    test('creates a project owned by the authenticated user', function () {
        $this->travelTo('2026-01-01 12:00:00');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/projects', [
            'name' => 'Website Redesign',
            'description' => 'Revamp the marketing site',
            'color' => Project::COLORS[0],
        ]);

        $response->assertCreated();

        $project = Project::sole();
        expect($project->user_id)->toBe($user->id);

        $response->assertExactJson([
            'data' => [
                'id' => $project->id,
                'name' => 'Website Redesign',
                'description' => 'Revamp the marketing site',
                'color' => Project::COLORS[0],
                'position' => 0,
                'created_at' => '2026-01-01T12:00:00.000000Z',
                'updated_at' => '2026-01-01T12:00:00.000000Z',
            ],
        ]);
    });

    test('assigns position 0 to the first project of a user', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/projects', [
            'name' => 'First project',
            'color' => Project::COLORS[0],
        ]);

        $response->assertJsonPath('data.position', 0);
    });

    test('assigns the next position after the current maximum for that user', function () {
        $user = User::factory()->create();
        Project::factory()->for($user)->create(['position' => 0]);
        Project::factory()->for($user)->create(['position' => 5]);

        $response = $this->actingAs($user)->postJson('/api/projects', [
            'name' => 'New project',
            'color' => Project::COLORS[1],
        ]);

        $response->assertJsonPath('data.position', 6);
    });

    test('ignores a client-supplied position and uses the server-computed value instead', function () {
        $user = User::factory()->create();
        Project::factory()->for($user)->create(['position' => 0]);

        $response = $this->actingAs($user)->postJson('/api/projects', [
            'name' => 'New project',
            'color' => Project::COLORS[1],
            'position' => 999,
        ]);

        $response->assertJsonPath('data.position', 1);
    });

    test('ignores a client-supplied user_id and keeps ownership with the authenticated user', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/projects', [
            'name' => 'New project',
            'color' => Project::COLORS[1],
            'user_id' => $otherUser->id,
        ]);

        $response->assertCreated();
        expect(Project::sole()->user_id)->toBe($user->id);
    });
});

describe('store validation', function () {
    test('rejects a missing name', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/projects', [
            'color' => Project::COLORS[0],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('name');
    });

    test('rejects an empty name', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/projects', [
            'name' => '',
            'color' => Project::COLORS[0],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('name');
    });

    test('rejects a name longer than 255 characters', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/projects', [
            'name' => str_repeat('a', 256),
            'color' => Project::COLORS[0],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('name');
    });

    test('rejects a missing color', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/projects', [
            'name' => 'New project',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('color');
    });

    test('rejects a color outside the approved palette', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/projects', [
            'name' => 'New project',
            'color' => '#000000',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('color');
    });

    test('accepts a null description', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/projects', [
            'name' => 'New project',
            'description' => null,
            'color' => Project::COLORS[0],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.description', null);
    });

    test('accepts a color from the approved palette', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/projects', [
            'name' => 'New project',
            'color' => Project::COLORS[2],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.color', Project::COLORS[2]);
    });
});

describe('show', function () {
    test('the owner can view their project', function () {
        $this->travelTo('2026-01-01 12:00:00');
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create([
            'name' => 'Website Redesign',
            'description' => 'Revamp the marketing site',
            'color' => Project::COLORS[0],
            'position' => 0,
        ]);

        $response = $this->actingAs($user)->getJson("/api/projects/{$project->id}");

        $response->assertOk();
        $response->assertExactJson([
            'data' => [
                'id' => $project->id,
                'name' => 'Website Redesign',
                'description' => 'Revamp the marketing site',
                'color' => Project::COLORS[0],
                'position' => 0,
                'created_at' => '2026-01-01T12:00:00.000000Z',
                'updated_at' => '2026-01-01T12:00:00.000000Z',
            ],
        ]);
    });

    test('a stranger cannot view another user\'s project and receives no project data', function () {
        $stranger = User::factory()->create();
        $project = Project::factory()->create(['name' => 'Secret project']);

        $response = $this->actingAs($stranger)->getJson("/api/projects/{$project->id}");

        $response->assertForbidden();
        expect($response->getContent())->not->toContain('Secret project');
    });

    test('a missing project returns 404', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/projects/999999')->assertNotFound();
    });
});

describe('update', function () {
    test('the owner can update only the given field, leaving the rest untouched', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create([
            'name' => 'Old name',
            'description' => 'Old description',
            'color' => Project::COLORS[0],
        ]);

        $response = $this->actingAs($user)->patchJson("/api/projects/{$project->id}", [
            'name' => 'New name',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'New name');
        $response->assertJsonPath('data.description', 'Old description');
        $response->assertJsonPath('data.color', Project::COLORS[0]);
    });

    test('description can be explicitly cleared to null', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['description' => 'Something']);

        $response = $this->actingAs($user)->patchJson("/api/projects/{$project->id}", [
            'description' => null,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.description', null);
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'description' => null]);
    });

    test('color can be changed to another approved value', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['color' => Project::COLORS[0]]);

        $response = $this->actingAs($user)->patchJson("/api/projects/{$project->id}", [
            'color' => Project::COLORS[1],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.color', Project::COLORS[1]);
    });

    test('rejects a color outside the approved palette', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->patchJson("/api/projects/{$project->id}", [
            'color' => '#000000',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('color');
    });

    test('rejects an empty name when present', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->patchJson("/api/projects/{$project->id}", [
            'name' => '',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('name');
    });

    test('rejects a name longer than 255 characters when present', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->patchJson("/api/projects/{$project->id}", [
            'name' => str_repeat('a', 256),
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('name');
    });

    test('ignores a client-supplied user_id', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)
            ->patchJson("/api/projects/{$project->id}", ['user_id' => $otherUser->id])
            ->assertOk();

        expect($project->fresh()->user_id)->toBe($user->id);
    });

    test('ignores a client-supplied position', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['position' => 3]);

        $this->actingAs($user)
            ->patchJson("/api/projects/{$project->id}", ['position' => 999])
            ->assertOk();

        expect($project->fresh()->position)->toBe(3);
    });

    test('an empty payload is a no-op that still returns 200', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)
            ->patchJson("/api/projects/{$project->id}", [])
            ->assertOk();
    });

    test('a stranger cannot update another user\'s project', function () {
        $stranger = User::factory()->create();
        $project = Project::factory()->create(['name' => 'Original name']);

        $this->actingAs($stranger)
            ->patchJson("/api/projects/{$project->id}", ['name' => 'Hacked'])
            ->assertForbidden();

        expect($project->fresh()->name)->toBe('Original name');
    });

    test('a missing project returns 404', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/api/projects/999999', ['name' => 'Whatever'])
            ->assertNotFound();
    });
});

describe('destroy', function () {
    test('the owner can delete their project', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->deleteJson("/api/projects/{$project->id}");

        $response->assertNoContent();
        expect($response->getContent())->toBe('');
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    });

    test('a stranger cannot delete another user\'s project', function () {
        $stranger = User::factory()->create();
        $project = Project::factory()->create();

        $this->actingAs($stranger)
            ->deleteJson("/api/projects/{$project->id}")
            ->assertForbidden();

        $this->assertModelExists($project);
    });

    test('a missing project returns 404', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->deleteJson('/api/projects/999999')->assertNotFound();
    });
});
