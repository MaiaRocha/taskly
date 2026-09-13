<?php

use App\Actions\RecordTaskActivity;
use App\Enums\TaskActivityType;
use App\Models\Task;
use App\Models\TaskActivity;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('an activity belongs to a task', function () {
    $task = Task::factory()->create();
    $activity = TaskActivity::factory()->for($task)->create();

    expect($activity->task->is($task))->toBeTrue();
});

test('a task has many activities', function () {
    $task = Task::factory()->create();
    TaskActivity::factory()->for($task)->count(3)->create();

    expect($task->activities()->count())->toBe(3);
});

test('type casts to the TaskActivityType enum', function () {
    $activity = TaskActivity::factory()->create(['type' => TaskActivityType::StatusChanged]);

    $fresh = TaskActivity::find($activity->id);

    expect($fresh->type)->toBeInstanceOf(TaskActivityType::class);
    expect($fresh->type)->toBe(TaskActivityType::StatusChanged);
});

test('data casts to an array', function () {
    $activity = TaskActivity::factory()->create(['data' => ['from' => 'not_started', 'to' => 'in_progress']]);

    $fresh = TaskActivity::find($activity->id);

    expect($fresh->data)->toBeArray();
    expect($fresh->data)->toBe(['from' => 'not_started', 'to' => 'in_progress']);
});

test('an activity has no updated_at column to manage', function () {
    $activity = TaskActivity::factory()->create();

    expect(TaskActivity::UPDATED_AT)->toBeNull();
    expect($activity->updated_at)->toBeNull();
});

test('task_id is not mass assignable, unlike type and data', function () {
    $task = Task::factory()->create();
    $otherTask = Task::factory()->create();

    $activity = new TaskActivity;
    $activity->fill([
        'task_id' => $otherTask->id,
        'type' => TaskActivityType::TaskCreated,
        'data' => ['x' => 1],
    ]);

    expect($activity->task_id)->toBeNull();
    expect($activity->type)->toBe(TaskActivityType::TaskCreated);
    expect($activity->data)->toBe(['x' => 1]);
});

describe('RecordTaskActivity', function () {
    test('creates an activity linked to the correct task', function () {
        $task = Task::factory()->create();
        $otherTask = Task::factory()->create();

        $activity = (new RecordTaskActivity)($task, TaskActivityType::TaskCreated);

        expect($activity->task_id)->toBe($task->id);
        expect($activity->task_id)->not->toBe($otherTask->id);
        $this->assertDatabaseHas('task_activities', ['id' => $activity->id, 'task_id' => $task->id]);
    });

    test('persists the exact type and data payload it was given', function () {
        $task = Task::factory()->create();

        $data = [
            'added' => [['id' => 1, 'name' => 'Urgente', 'color' => '#EC4899']],
            'removed' => [],
        ];

        $activity = (new RecordTaskActivity)($task, TaskActivityType::TagsChanged, $data);

        expect($activity->type)->toBe(TaskActivityType::TagsChanged);
        expect($activity->data)->toBe($data);

        $fresh = TaskActivity::find($activity->id);
        expect($fresh->type)->toBe(TaskActivityType::TagsChanged);
        expect($fresh->data)->toBe($data);
    });

    test('defaults to an empty data payload when none is given', function () {
        $task = Task::factory()->create();

        $activity = (new RecordTaskActivity)($task, TaskActivityType::TaskCreated);

        expect($activity->data)->toBe([]);
    });
});

test('deleting a task removes its activities via cascade', function () {
    $task = Task::factory()->create();
    $activity = TaskActivity::factory()->for($task)->create();

    $task->delete();

    $this->assertDatabaseMissing('task_activities', ['id' => $activity->id]);
});
