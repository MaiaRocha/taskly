<?php

use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('a tag belongs to its user, and the user has many tags', function () {
    $user = User::factory()->create();
    $tags = Tag::factory()->for($user)->count(2)->create();

    expect($tags->first()->user->is($user))->toBeTrue();
    expect($user->tags)->toHaveCount(2);
});

test('a tag belongs to many tasks', function () {
    $tag = Tag::factory()->create();
    $tasks = Task::factory()->count(2)->create();
    $tag->tasks()->attach($tasks);

    expect($tag->tasks)->toHaveCount(2);
    expect($tasks->first()->fresh()->tags->contains($tag))->toBeTrue();
});

test('deleting a tag detaches it from tasks without deleting the tasks', function () {
    $tag = Tag::factory()->create();
    $task = Task::factory()->create();
    $tag->tasks()->attach($task);

    $tag->delete();

    $this->assertDatabaseMissing('tag_task', ['tag_id' => $tag->id, 'task_id' => $task->id]);
    $this->assertDatabaseHas('tasks', ['id' => $task->id]);
});

describe('name normalization', function () {
    test('creating a tag trims the name and derives a lowercase normalized_name', function () {
        $tag = Tag::factory()->create(['name' => '  Urgente  ']);

        expect($tag->name)->toBe('Urgente');
        expect($tag->normalized_name)->toBe('urgente');
    });

    test('updating the name recalculates normalized_name', function () {
        $tag = Tag::factory()->create(['name' => 'Urgente']);

        $tag->name = 'Backlog';
        $tag->save();

        expect($tag->normalized_name)->toBe('backlog');
    });

    test('normalization preserves accents and internal spacing', function () {
        $tag = Tag::factory()->create(['name' => 'Café da Manhã']);

        expect($tag->name)->toBe('Café da Manhã');
        expect($tag->normalized_name)->toBe('café da manhã');
    });

    test('the same normalized_name is rejected for the same user', function () {
        $user = User::factory()->create();
        Tag::factory()->for($user)->create(['name' => 'Urgente']);

        Tag::factory()->for($user)->create(['name' => ' URGENTE ']);
    })->throws(QueryException::class);

    test('the same normalized_name is allowed for different users', function () {
        Tag::factory()->create(['name' => 'Urgente']);
        $secondTag = Tag::factory()->create(['name' => 'Urgente']);

        expect($secondTag->normalized_name)->toBe('urgente');
    });

    test('normalized_name cannot be set through mass assignment', function () {
        $user = User::factory()->create();

        $tag = $user->tags()->create([
            'name' => 'Foo',
            'normalized_name' => 'hacked',
            'color' => '#635BFF',
        ]);

        expect($tag->normalized_name)->toBe('foo');
    });
});
