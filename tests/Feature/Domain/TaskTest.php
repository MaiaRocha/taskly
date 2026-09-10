<?php

use App\Enums\TaskStatus;
use App\Models\Attachment;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('a task belongs to a project, has many tags, and has many attachments', function () {
    $project = Project::factory()->create();
    $task = Task::factory()->for($project)->create();
    $tags = Tag::factory()->for($project->user)->count(2)->create();
    $task->tags()->attach($tags);
    $attachments = Attachment::factory()->for($task)->count(2)->create();

    expect($task->project->is($project))->toBeTrue();
    expect($task->tags)->toHaveCount(2);
    expect($tags->first()->fresh()->tasks->contains($task))->toBeTrue();
    expect($task->attachments)->toHaveCount(2);
    expect($attachments->first()->task->is($task))->toBeTrue();
});

test('deleting a task deletes its attachments and detaches tags without deleting them', function () {
    $task = Task::factory()->create();
    $tag = Tag::factory()->for($task->project->user)->create();
    $task->tags()->attach($tag);
    $attachment = Attachment::factory()->for($task)->create();

    $task->delete();

    $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
    $this->assertDatabaseMissing('tag_task', ['task_id' => $task->id, 'tag_id' => $tag->id]);
    $this->assertDatabaseHas('tags', ['id' => $tag->id]);
});

test('status is cast to TaskStatus and persists its backed value', function () {
    $task = Task::factory()->create();

    expect($task->status)->toBeInstanceOf(TaskStatus::class);
    expect($task->status)->toBe(TaskStatus::NotStarted);

    $this->assertDatabaseHas('tasks', [
        'id' => $task->id,
        'status' => 'not_started',
    ]);
});

describe('completed_at', function () {
    test('a newly created task defaults to not started with no completed_at', function () {
        $task = Task::factory()->create();

        expect($task->status)->toBe(TaskStatus::NotStarted);
        expect($task->completed_at)->toBeNull();
    });

    test('moving from not started to completed fills completed_at', function () {
        $now = now();
        $this->travelTo($now);

        $task = Task::factory()->create();
        $task->status = TaskStatus::Completed;
        $task->save();

        expect($task->completed_at)->not->toBeNull();
        // completed_at is a `dateTime` column (second precision, no fractional
        // seconds), while $now retains microseconds — compare at the
        // precision the column actually stores.
        expect($task->completed_at->format('Y-m-d H:i:s'))->toBe($now->format('Y-m-d H:i:s'));
    });

    test('saving an already completed task again without changing status does not overwrite completed_at', function () {
        $completedAt = now();
        $this->travelTo($completedAt);

        $task = Task::factory()->completed()->create();

        $this->travel(1)->day();
        $task->title = 'An updated title, status untouched';
        $task->save();

        expect($task->completed_at->format('Y-m-d H:i:s'))->toBe($completedAt->format('Y-m-d H:i:s'));
    });

    test('moving from completed to in progress clears completed_at', function () {
        $task = Task::factory()->completed()->create();

        $task->status = TaskStatus::InProgress;
        $task->save();

        expect($task->completed_at)->toBeNull();
    });

    test('moving from completed to cancelled clears completed_at', function () {
        $task = Task::factory()->completed()->create();

        $task->status = TaskStatus::Cancelled;
        $task->save();

        expect($task->completed_at)->toBeNull();
    });

    test('moving from not started to cancelled leaves completed_at null', function () {
        $task = Task::factory()->create();

        $task->status = TaskStatus::Cancelled;
        $task->save();

        expect($task->completed_at)->toBeNull();
    });
});

describe('overdue', function () {
    test('overdue reflects the due date and status', function (TaskStatus $status, bool $isPastDue, bool $expected) {
        $this->travelTo(now());

        $task = Task::factory()->create([
            'status' => $status,
            'due_at' => $isPastDue ? now()->subDay() : now()->addDay(),
        ]);

        expect($task->overdue)->toBe($expected);
    })->with([
        'past due, not started' => [TaskStatus::NotStarted, true, true],
        'past due, in progress' => [TaskStatus::InProgress, true, true],
        'past due, completed' => [TaskStatus::Completed, true, false],
        'past due, cancelled' => [TaskStatus::Cancelled, true, false],
        'future due date' => [TaskStatus::NotStarted, false, false],
    ]);

    test('overdue is false when there is no due date', function () {
        $task = Task::factory()->create(['due_at' => null, 'status' => TaskStatus::NotStarted]);

        expect($task->overdue)->toBeFalse();
    });
});
