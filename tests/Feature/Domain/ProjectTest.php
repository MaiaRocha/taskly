<?php

use App\Models\Attachment;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('a project belongs to its user, and the user has many projects', function () {
    $user = User::factory()->create();
    $projects = Project::factory()->for($user)->count(2)->create();

    expect($projects->first()->user->is($user))->toBeTrue();
    expect($user->projects)->toHaveCount(2);
    expect($user->projects->pluck('id')->sort()->values()->all())
        ->toBe($projects->pluck('id')->sort()->values()->all());
});

test('a project has many tasks', function () {
    $project = Project::factory()->create();
    $tasks = Task::factory()->for($project)->count(3)->create();

    expect($project->tasks)->toHaveCount(3);
    expect($tasks->first()->project->is($project))->toBeTrue();
});

test('deleting a project deletes its tasks and their attachments, and detaches shared tags without deleting them', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $tag = Tag::factory()->for($user)->create();

    $tasks = Task::factory()->for($project)->count(2)->create();
    $tasks->each(fn (Task $task) => $task->tags()->attach($tag));

    $attachments = $tasks->map(fn (Task $task) => Attachment::factory()->for($task)->create());

    $project->delete();

    $this->assertDatabaseMissing('projects', ['id' => $project->id]);

    foreach ($tasks as $task) {
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    foreach ($attachments as $attachment) {
        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
    }

    foreach ($tasks as $task) {
        $this->assertDatabaseMissing('tag_task', ['task_id' => $task->id, 'tag_id' => $tag->id]);
    }

    // The tag itself belongs to the user, not the project — it must survive.
    $this->assertDatabaseHas('tags', ['id' => $tag->id]);
});
