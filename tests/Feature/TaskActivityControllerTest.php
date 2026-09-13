<?php

use App\Enums\TaskActivityType;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

describe('authentication', function () {
    test('a guest cannot list a task\'s activities', function () {
        $task = Task::factory()->create();

        $this->getJson("/api/tasks/{$task->id}/activities")->assertUnauthorized();
    });
});

describe('route contract', function () {
    test('no write verb exists for the activities endpoint', function (string $method) {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();

        $this->actingAs($user)
            ->json($method, "/api/tasks/{$task->id}/activities")
            ->assertMethodNotAllowed();
    })->with([
        'POST' => ['POST'],
        'PATCH' => ['PATCH'],
        'PUT' => ['PUT'],
        'DELETE' => ['DELETE'],
    ]);
});

describe('index', function () {
    test('the owner can list activities for their own task', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();
        TaskActivity::factory()->for($task)->create();

        $this->actingAs($user)
            ->getJson("/api/tasks/{$task->id}/activities")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    });

    test('a stranger cannot list activities of another user\'s task', function () {
        $stranger = User::factory()->create();
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();
        TaskActivity::factory()->for($task)->create();

        $this->actingAs($stranger)
            ->getJson("/api/tasks/{$task->id}/activities")
            ->assertForbidden();
    });

    test('a missing task returns 404', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/tasks/999999/activities')
            ->assertNotFound();
    });

    test('the response contract exposes exactly id, type, data, created_at', function () {
        $this->travelTo('2026-09-15 21:00:00');
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();
        $activity = TaskActivity::factory()->for($task)->create([
            'type' => TaskActivityType::StatusChanged,
            'data' => ['from' => 'not_started', 'to' => 'in_progress'],
        ]);

        $response = $this->actingAs($user)->getJson("/api/tasks/{$task->id}/activities");

        $response->assertOk();
        $response->assertJsonPath('data.0', [
            'id' => $activity->id,
            'type' => 'status_changed',
            'data' => ['from' => 'not_started', 'to' => 'in_progress'],
            'created_at' => '2026-09-15T21:00:00Z',
        ]);
        $response->assertJsonMissingPath('data.0.task_id');
        $response->assertJsonMissingPath('data.0.updated_at');
    });

    test('only activities belonging to the requested task are returned', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();
        $otherTask = Task::factory()->for($project)->create();
        $activity = TaskActivity::factory()->for($task)->create();
        TaskActivity::factory()->for($otherTask)->create();

        $response = $this->actingAs($user)->getJson("/api/tasks/{$task->id}/activities");

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $activity->id);
    });

    test('orders by created_at descending, most recent first', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();

        $this->travelTo('2026-01-01 12:00:00');
        $oldest = TaskActivity::factory()->for($task)->create();

        $this->travelTo('2026-01-02 12:00:00');
        $newest = TaskActivity::factory()->for($task)->create();

        $response = $this->actingAs($user)->getJson("/api/tasks/{$task->id}/activities");

        $response->assertJsonPath('data.0.id', $newest->id);
        $response->assertJsonPath('data.1.id', $oldest->id);
    });

    test('breaks a created_at tie by id descending', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();

        $this->travelTo('2026-01-01 12:00:00');
        $first = TaskActivity::factory()->for($task)->create();
        $second = TaskActivity::factory()->for($task)->create();

        $response = $this->actingAs($user)->getJson("/api/tasks/{$task->id}/activities");

        $response->assertJsonPath('data.0.id', $second->id);
        $response->assertJsonPath('data.1.id', $first->id);
    });

    test('paginates 20 activities per page', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();
        TaskActivity::factory()->for($task)->count(25)->create();

        $response = $this->actingAs($user)->getJson("/api/tasks/{$task->id}/activities");

        $response->assertOk();
        $response->assertJsonCount(20, 'data');
        $response->assertJsonPath('meta.current_page', 1);
        $response->assertJsonPath('meta.last_page', 2);
        $response->assertJsonPath('meta.total', 25);
    });

    test('page 2 returns the remaining activities', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();
        TaskActivity::factory()->for($task)->count(25)->create();

        $response = $this->actingAs($user)->getJson("/api/tasks/{$task->id}/activities?page=2");

        $response->assertOk();
        $response->assertJsonCount(5, 'data');
        $response->assertJsonPath('meta.current_page', 2);
    });
});
