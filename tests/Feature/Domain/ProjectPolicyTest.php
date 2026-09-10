<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('the owner can view, update, and delete their project', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create();

    expect($owner->can('view', $project))->toBeTrue();
    expect($owner->can('update', $project))->toBeTrue();
    expect($owner->can('delete', $project))->toBeTrue();
});

test('a stranger cannot view, update, or delete another user\'s project', function () {
    $stranger = User::factory()->create();
    $project = Project::factory()->create();

    expect($stranger->cannot('view', $project))->toBeTrue();
    expect($stranger->cannot('update', $project))->toBeTrue();
    expect($stranger->cannot('delete', $project))->toBeTrue();
});

test('any authenticated user can create a project', function () {
    $user = User::factory()->create();

    expect($user->can('create', Project::class))->toBeTrue();
});
