<?php

use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('the owner can view, update, and delete their tag', function () {
    $owner = User::factory()->create();
    $tag = Tag::factory()->for($owner)->create();

    expect($owner->can('view', $tag))->toBeTrue();
    expect($owner->can('update', $tag))->toBeTrue();
    expect($owner->can('delete', $tag))->toBeTrue();
});

test('a stranger cannot view, update, or delete another user\'s tag', function () {
    $stranger = User::factory()->create();
    $tag = Tag::factory()->create();

    expect($stranger->cannot('view', $tag))->toBeTrue();
    expect($stranger->cannot('update', $tag))->toBeTrue();
    expect($stranger->cannot('delete', $tag))->toBeTrue();
});

test('any authenticated user can create a tag', function () {
    $user = User::factory()->create();

    expect($user->can('create', Tag::class))->toBeTrue();
});
