<?php

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

describe('authentication', function () {
    test('a guest cannot access any task endpoint', function (string $method, callable $uri) {
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();

        $this->json($method, $uri($project, $task))->assertUnauthorized();
    })->with([
        'index' => ['GET', fn (Project $project, Task $task) => "/api/projects/{$project->id}/tasks"],
        'store' => ['POST', fn (Project $project, Task $task) => "/api/projects/{$project->id}/tasks"],
        'show' => ['GET', fn (Project $project, Task $task) => "/api/tasks/{$task->id}"],
        'update' => ['PATCH', fn (Project $project, Task $task) => "/api/tasks/{$task->id}"],
        'destroy' => ['DELETE', fn (Project $project, Task $task) => "/api/tasks/{$task->id}"],
    ]);
});

describe('route contract', function () {
    test('PUT is not an allowed method for a task', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();

        $this->actingAs($user)
            ->put("/api/tasks/{$task->id}", ['title' => 'Renamed'])
            ->assertMethodNotAllowed();
    });

    test('the move endpoint does not exist yet', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();

        $this->actingAs($user)
            ->patchJson("/api/tasks/{$task->id}/move", ['status' => 'in_progress'])
            ->assertNotFound();
    });
});

describe('index', function () {
    test('returns only the tasks belonging to the given project, ordered by position then id', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $otherProjectSameUser = Project::factory()->for($user)->create();
        $stranger = User::factory()->create();
        $strangerProject = Project::factory()->for($stranger)->create();

        $second = Task::factory()->for($project)->create(['position' => 1]);
        $first = Task::factory()->for($project)->create(['position' => 0]);
        Task::factory()->for($otherProjectSameUser)->create();
        Task::factory()->for($strangerProject)->create();

        $response = $this->actingAs($user)->getJson("/api/projects/{$project->id}/tasks");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.id', $first->id);
        $response->assertJsonPath('data.1.id', $second->id);
    });

    test('breaks a position tie by id ascending', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $olderTiedTask = Task::factory()->for($project)->create(['position' => 5]);
        $newerTiedTask = Task::factory()->for($project)->create(['position' => 5]);

        $response = $this->actingAs($user)->getJson("/api/projects/{$project->id}/tasks");

        $response->assertJsonPath('data.0.id', $olderTiedTask->id);
        $response->assertJsonPath('data.1.id', $newerTiedTask->id);
    });

    test('exposes only the documented fields, wrapped in data, with no pagination metadata', function () {
        $this->travelTo('2026-01-01 12:00:00');
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create([
            'title' => 'Design landing page',
            'short_description' => 'Hero + CTA',
            'description' => 'Full brief in the doc',
            'status' => TaskStatus::InProgress,
            'due_at' => null,
            'position' => 0,
        ]);

        $response = $this->actingAs($user)->getJson("/api/projects/{$project->id}/tasks");

        $response->assertOk();
        $response->assertExactJson([
            'data' => [
                [
                    'id' => $task->id,
                    'project_id' => $project->id,
                    'title' => 'Design landing page',
                    'short_description' => 'Hero + CTA',
                    'description' => 'Full brief in the doc',
                    'status' => 'in_progress',
                    'due_at' => null,
                    'position' => 0,
                    'completed_at' => null,
                    'overdue' => false,
                    'created_at' => '2026-01-01T12:00:00.000000Z',
                    'updated_at' => '2026-01-01T12:00:00.000000Z',
                ],
            ],
        ]);
    });

    test('a stranger cannot list tasks of another user\'s project', function () {
        $stranger = User::factory()->create();
        $project = Project::factory()->create();
        Task::factory()->for($project)->create();

        $this->actingAs($stranger)
            ->getJson("/api/projects/{$project->id}/tasks")
            ->assertForbidden();
    });

    test('a missing project returns 404', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/projects/999999/tasks')->assertNotFound();
    });
});

describe('store', function () {
    test('creates a task belonging to the project in the url', function () {
        $this->travelTo('2026-01-01 12:00:00');
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->postJson("/api/projects/{$project->id}/tasks", [
            'title' => 'Design landing page',
            'short_description' => 'Hero + CTA',
            'description' => 'Full brief in the doc',
            'status' => 'in_progress',
        ]);

        $response->assertCreated();

        $task = Task::sole();
        expect($task->project_id)->toBe($project->id);

        $response->assertExactJson([
            'data' => [
                'id' => $task->id,
                'project_id' => $project->id,
                'title' => 'Design landing page',
                'short_description' => 'Hero + CTA',
                'description' => 'Full brief in the doc',
                'status' => 'in_progress',
                'due_at' => null,
                'position' => 0,
                'completed_at' => null,
                'overdue' => false,
                'created_at' => '2026-01-01T12:00:00.000000Z',
                'updated_at' => '2026-01-01T12:00:00.000000Z',
            ],
        ]);
    });

    test('defaults status to not_started when omitted', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->postJson("/api/projects/{$project->id}/tasks", [
            'title' => 'Untitled task',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'not_started');
        $this->assertDatabaseHas('tasks', ['id' => Task::sole()->id, 'status' => 'not_started']);
    });

    test('accepts an explicit status', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->postJson("/api/projects/{$project->id}/tasks", [
            'title' => 'In progress task',
            'status' => 'in_progress',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'in_progress');
    });

    test('rejects an explicit null status', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->postJson("/api/projects/{$project->id}/tasks", [
            'title' => 'Untitled task',
            'status' => null,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('status');
    });
});

describe('store ownership and payload safety', function () {
    test('a stranger cannot create a task in another user\'s project', function () {
        $stranger = User::factory()->create();
        $project = Project::factory()->create();

        $this->actingAs($stranger)
            ->postJson("/api/projects/{$project->id}/tasks", ['title' => 'Hacked'])
            ->assertForbidden();

        expect(Task::count())->toBe(0);
    });

    test('ignores a client-supplied project_id and keeps the task under the url project', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $otherProject = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->postJson("/api/projects/{$project->id}/tasks", [
            'title' => 'New task',
            'project_id' => $otherProject->id,
        ]);

        $response->assertCreated();
        expect(Task::sole()->project_id)->toBe($project->id);
    });

    test('ignores a client-supplied completed_at', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->postJson("/api/projects/{$project->id}/tasks", [
            'title' => 'New task',
            'completed_at' => '2020-01-01T00:00:00Z',
        ]);

        $response->assertCreated();
        expect(Task::sole()->completed_at)->toBeNull();
    });

    test('ignores client-supplied tags and attachments', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->postJson("/api/projects/{$project->id}/tasks", [
            'title' => 'New task',
            'tags' => ['urgent'],
            'attachments' => ['file.pdf'],
        ]);

        $response->assertCreated();

        $task = Task::sole();
        expect($task->tags()->count())->toBe(0);
        expect($task->attachments()->count())->toBe(0);
    });
});

describe('store position', function () {
    test('assigns position 0 to the first task in a project', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->postJson("/api/projects/{$project->id}/tasks", [
            'title' => 'First task',
        ]);

        $response->assertJsonPath('data.position', 0);
    });

    test('assigns the next position after the current maximum in the project', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        Task::factory()->for($project)->create(['position' => 0]);
        Task::factory()->for($project)->create(['position' => 5]);

        $response = $this->actingAs($user)->postJson("/api/projects/{$project->id}/tasks", [
            'title' => 'New task',
        ]);

        $response->assertJsonPath('data.position', 6);
    });

    test('ignores a client-supplied position and uses the server-computed value instead', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        Task::factory()->for($project)->create(['position' => 0]);

        $response = $this->actingAs($user)->postJson("/api/projects/{$project->id}/tasks", [
            'title' => 'New task',
            'position' => 999,
        ]);

        $response->assertJsonPath('data.position', 1);
    });

    test('scopes the position calculation to the project in the url, not other projects', function () {
        $user = User::factory()->create();
        $projectA = Project::factory()->for($user)->create();
        $projectB = Project::factory()->for($user)->create();
        Task::factory()->for($projectA)->create(['position' => 2]);
        Task::factory()->for($projectB)->create(['position' => 50]);

        $response = $this->actingAs($user)->postJson("/api/projects/{$projectA->id}/tasks", [
            'title' => 'New task in A',
        ]);

        $response->assertJsonPath('data.position', 3);
    });
});

describe('store validation', function () {
    test('rejects a missing title', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->postJson("/api/projects/{$project->id}/tasks", []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('title');
    });

    test('rejects an empty title', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->postJson("/api/projects/{$project->id}/tasks", [
            'title' => '',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('title');
    });

    test('rejects a title longer than 255 characters', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->postJson("/api/projects/{$project->id}/tasks", [
            'title' => str_repeat('a', 256),
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('title');
    });

    test('rejects a short_description longer than 255 characters', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->postJson("/api/projects/{$project->id}/tasks", [
            'title' => 'New task',
            'short_description' => str_repeat('a', 256),
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('short_description');
    });

    test('accepts a null short_description', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->postJson("/api/projects/{$project->id}/tasks", [
            'title' => 'New task',
            'short_description' => null,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.short_description', null);
    });

    test('accepts a null description', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->postJson("/api/projects/{$project->id}/tasks", [
            'title' => 'New task',
            'description' => null,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.description', null);
    });

    test('rejects an invalid status value', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->postJson("/api/projects/{$project->id}/tasks", [
            'title' => 'New task',
            'status' => 'not_a_status',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('status');
    });

    test('accepts a null due_at', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->postJson("/api/projects/{$project->id}/tasks", [
            'title' => 'New task',
            'due_at' => null,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.due_at', null);
    });

    test('accepts a due_at value with an unambiguous timezone', function (string $dueAt) {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->postJson("/api/projects/{$project->id}/tasks", [
            'title' => 'New task',
            'due_at' => $dueAt,
        ]);

        $response->assertCreated();
    })->with([
        'negative offset' => ['2026-12-15T18:00:00-03:00'],
        'Z suffix' => ['2026-12-15T21:00:00Z'],
        'Z suffix with fractional seconds' => ['2026-12-15T21:00:00.000Z'],
        'in the past' => ['2020-01-01T00:00:00Z'],
    ]);

    test('rejects a due_at value without an unambiguous timezone', function (string $dueAt) {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->postJson("/api/projects/{$project->id}/tasks", [
            'title' => 'New task',
            'due_at' => $dueAt,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('due_at');
    })->with([
        'no timezone' => ['2026-12-15T18:00:00'],
        'space instead of T' => ['2026-12-15 18:00:00'],
        'not a date at all' => ['not-a-date'],
    ]);
});

describe('due_at UTC normalization', function () {
    test('normalizes an offset due_at to UTC on create', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->postJson("/api/projects/{$project->id}/tasks", [
            'title' => 'Launch',
            'due_at' => '2026-12-15T18:00:00-03:00',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.due_at', '2026-12-15T21:00:00.000000Z');

        $task = Task::sole();
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'due_at' => '2026-12-15 21:00:00']);
    });

    test('normalizes an offset due_at to UTC on update', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create(['due_at' => null]);

        $response = $this->actingAs($user)->patchJson("/api/tasks/{$task->id}", [
            'due_at' => '2026-12-15T18:00:00-03:00',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.due_at', '2026-12-15T21:00:00.000000Z');
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'due_at' => '2026-12-15 21:00:00']);
    });

    test('clears due_at when explicitly set to null via PATCH', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create(['due_at' => '2026-06-01 12:00:00']);

        $response = $this->actingAs($user)->patchJson("/api/tasks/{$task->id}", [
            'due_at' => null,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.due_at', null);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'due_at' => null]);
    });
});

describe('show', function () {
    test('the owner can view their task', function () {
        $this->travelTo('2026-01-01 12:00:00');
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create([
            'title' => 'Design landing page',
            'short_description' => 'Hero + CTA',
            'description' => 'Full brief in the doc',
            'status' => TaskStatus::NotStarted,
            'due_at' => null,
            'position' => 0,
        ]);

        $response = $this->actingAs($user)->getJson("/api/tasks/{$task->id}");

        $response->assertOk();
        $response->assertExactJson([
            'data' => [
                'id' => $task->id,
                'project_id' => $project->id,
                'title' => 'Design landing page',
                'short_description' => 'Hero + CTA',
                'description' => 'Full brief in the doc',
                'status' => 'not_started',
                'due_at' => null,
                'position' => 0,
                'completed_at' => null,
                'overdue' => false,
                'created_at' => '2026-01-01T12:00:00.000000Z',
                'updated_at' => '2026-01-01T12:00:00.000000Z',
            ],
        ]);
    });

    test('a stranger cannot view a task from another user\'s project and receives no task data', function () {
        $stranger = User::factory()->create();
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create(['title' => 'Secret task']);

        $response = $this->actingAs($stranger)->getJson("/api/tasks/{$task->id}");

        $response->assertForbidden();
        expect($response->getContent())->not->toContain('Secret task');
    });

    test('a missing task returns 404', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/tasks/999999')->assertNotFound();
    });
});

describe('update', function () {
    test('updates only the given field, leaving the rest untouched', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create([
            'title' => 'Old title',
            'short_description' => 'Old short',
            'description' => 'Old description',
        ]);

        $response = $this->actingAs($user)->patchJson("/api/tasks/{$task->id}", [
            'title' => 'New title',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.title', 'New title');
        $response->assertJsonPath('data.short_description', 'Old short');
        $response->assertJsonPath('data.description', 'Old description');
    });

    test('short_description can be explicitly cleared to null', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create(['short_description' => 'Something']);

        $response = $this->actingAs($user)->patchJson("/api/tasks/{$task->id}", [
            'short_description' => null,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.short_description', null);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'short_description' => null]);
    });

    test('description can be explicitly cleared to null', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create(['description' => 'Something']);

        $response = $this->actingAs($user)->patchJson("/api/tasks/{$task->id}", [
            'description' => null,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.description', null);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'description' => null]);
    });

    test('status can be changed to another valid value', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create(['status' => TaskStatus::NotStarted]);

        $response = $this->actingAs($user)->patchJson("/api/tasks/{$task->id}", [
            'status' => 'in_progress',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'in_progress');
    });

    test('rejects an invalid status value when present', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();

        $response = $this->actingAs($user)->patchJson("/api/tasks/{$task->id}", [
            'status' => 'not_a_status',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('status');
    });

    test('rejects an empty title when present', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();

        $response = $this->actingAs($user)->patchJson("/api/tasks/{$task->id}", [
            'title' => '',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('title');
    });

    test('rejects a title longer than 255 characters when present', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();

        $response = $this->actingAs($user)->patchJson("/api/tasks/{$task->id}", [
            'title' => str_repeat('a', 256),
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('title');
    });

    test('rejects a due_at value without an unambiguous timezone when present', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();

        $response = $this->actingAs($user)->patchJson("/api/tasks/{$task->id}", [
            'due_at' => '2026-12-15T18:00:00',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('due_at');
    });

    test('ignores a client-supplied project_id', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $otherProject = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();

        $this->actingAs($user)
            ->patchJson("/api/tasks/{$task->id}", ['project_id' => $otherProject->id])
            ->assertOk();

        expect($task->fresh()->project_id)->toBe($project->id);
    });

    test('ignores a client-supplied position', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create(['position' => 3]);

        $this->actingAs($user)
            ->patchJson("/api/tasks/{$task->id}", ['position' => 999])
            ->assertOk();

        expect($task->fresh()->position)->toBe(3);
    });

    test('does not let the client set completed_at directly', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create(['status' => TaskStatus::NotStarted]);

        $this->actingAs($user)
            ->patchJson("/api/tasks/{$task->id}", ['completed_at' => '2020-01-01T00:00:00Z'])
            ->assertOk();

        expect($task->fresh()->completed_at)->toBeNull();
    });

    test('a stranger cannot update a task from another user\'s project', function () {
        $stranger = User::factory()->create();
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create(['title' => 'Original title']);

        $this->actingAs($stranger)
            ->patchJson("/api/tasks/{$task->id}", ['title' => 'Hacked'])
            ->assertForbidden();

        expect($task->fresh()->title)->toBe('Original title');
    });

    test('a missing task returns 404', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/api/tasks/999999', ['title' => 'Whatever'])
            ->assertNotFound();
    });
});

describe('completed_at integration', function () {
    test('completing a task via PATCH sets completed_at', function () {
        $this->travelTo('2026-01-01 12:00:00');
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create(['status' => TaskStatus::NotStarted]);

        $response = $this->actingAs($user)->patchJson("/api/tasks/{$task->id}", [
            'status' => 'completed',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'completed');
        $response->assertJsonPath('data.completed_at', '2026-01-01T12:00:00.000000Z');
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => 'completed',
            'completed_at' => '2026-01-01 12:00:00',
        ]);
    });

    test('moving a completed task back to in_progress clears completed_at', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->completed()->create();

        $response = $this->actingAs($user)->patchJson("/api/tasks/{$task->id}", [
            'status' => 'in_progress',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.completed_at', null);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'completed_at' => null]);
    });
});

describe('overdue', function () {
    test('marks an active task with a past due date as overdue', function () {
        $this->travelTo('2026-06-01 12:00:00');
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create([
            'status' => TaskStatus::InProgress,
            'due_at' => '2026-05-01T00:00:00Z',
        ]);

        $response = $this->actingAs($user)->getJson("/api/tasks/{$task->id}");

        $response->assertJsonPath('data.overdue', true);
    });
});

describe('destroy', function () {
    test('the owner can delete their task', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();

        $response = $this->actingAs($user)->deleteJson("/api/tasks/{$task->id}");

        $response->assertNoContent();
        expect($response->getContent())->toBe('');
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    });

    test('a stranger cannot delete a task from another user\'s project', function () {
        $stranger = User::factory()->create();
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();

        $this->actingAs($stranger)
            ->deleteJson("/api/tasks/{$task->id}")
            ->assertForbidden();

        $this->assertModelExists($task);
    });

    test('a missing task returns 404', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->deleteJson('/api/tasks/999999')->assertNotFound();
    });
});
