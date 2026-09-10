<?php

use App\Models\Attachment;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('the owning chain user can view and delete an attachment', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create();
    $task = Task::factory()->for($project)->create();
    $attachment = Attachment::factory()->for($task)->create();

    expect($owner->can('view', $attachment))->toBeTrue();
    expect($owner->can('delete', $attachment))->toBeTrue();
});

test('a stranger cannot view or delete an attachment from another user\'s task', function () {
    $stranger = User::factory()->create();
    $attachment = Attachment::factory()->create();

    expect($stranger->cannot('view', $attachment))->toBeTrue();
    expect($stranger->cannot('delete', $attachment))->toBeTrue();
});

test('creating an attachment is allowed for the owning chain and denied for a stranger', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create();
    $task = Task::factory()->for($project)->create();
    $stranger = User::factory()->create();

    expect($owner->can('create', [Attachment::class, $task]))->toBeTrue();
    expect($stranger->cannot('create', [Attachment::class, $task]))->toBeTrue();
});
