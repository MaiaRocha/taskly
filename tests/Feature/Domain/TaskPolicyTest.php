<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('the project owner can view, update, and delete a task in their project', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create();
    $task = Task::factory()->for($project)->create();

    expect($owner->can('view', $task))->toBeTrue();
    expect($owner->can('update', $task))->toBeTrue();
    expect($owner->can('delete', $task))->toBeTrue();
});

test('a stranger cannot view, update, or delete a task from another user\'s project', function () {
    $stranger = User::factory()->create();
    $task = Task::factory()->create();

    expect($stranger->cannot('view', $task))->toBeTrue();
    expect($stranger->cannot('update', $task))->toBeTrue();
    expect($stranger->cannot('delete', $task))->toBeTrue();
});

test('creating a task is allowed for the project owner and denied for a stranger', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create();
    $stranger = User::factory()->create();

    expect($owner->can('create', [Task::class, $project]))->toBeTrue();
    expect($stranger->cannot('create', [Task::class, $project]))->toBeTrue();
});
