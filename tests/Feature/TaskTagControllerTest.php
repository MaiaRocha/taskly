<?php

use App\Enums\TaskActivityType;
use App\Models\Attachment;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

describe('authentication', function () {
    test('a guest cannot sync tags on a task', function () {
        $task = Task::factory()->create();

        $this->putJson("/api/tasks/{$task->id}/tags", ['tag_ids' => []])
            ->assertUnauthorized();
    });
});

describe('sync', function () {
    test('the owner can sync their own tags onto their task', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();
        $tag1 = $user->tags()->create(['name' => 'Urgent', 'color' => '#EC4899']);
        $tag2 = $user->tags()->create(['name' => 'Backend', 'color' => '#3B82F6']);

        $response = $this->actingAs($user)->putJson("/api/tasks/{$task->id}/tags", [
            'tag_ids' => [$tag1->id, $tag2->id],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('tag_task', ['task_id' => $task->id, 'tag_id' => $tag1->id]);
        $this->assertDatabaseHas('tag_task', ['task_id' => $task->id, 'tag_id' => $tag2->id]);
        $response->assertJsonCount(2, 'data.tags');
    });

    test('the response embeds tags as TagResource, without pivot/user_id/normalized_name', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();
        $tag = $user->tags()->create(['name' => 'Urgent', 'color' => '#EC4899']);

        $response = $this->actingAs($user)->putJson("/api/tasks/{$task->id}/tags", [
            'tag_ids' => [$tag->id],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.tags.0.id', $tag->id);
        $response->assertJsonPath('data.tags.0.name', 'Urgent');
        $response->assertJsonPath('data.tags.0.color', '#EC4899');
        $response->assertJsonMissingPath('data.tags.0.user_id');
        $response->assertJsonMissingPath('data.tags.0.normalized_name');
        $response->assertJsonMissingPath('data.tags.0.pivot');
    });

    test('replaces the previous set of tags instead of merging with it', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();
        $tag1 = $user->tags()->create(['name' => 'Tag1', 'color' => '#EC4899']);
        $tag2 = $user->tags()->create(['name' => 'Tag2', 'color' => '#3B82F6']);
        $tag3 = $user->tags()->create(['name' => 'Tag3', 'color' => '#22C55E']);
        $task->tags()->attach([$tag1->id, $tag2->id]);

        $response = $this->actingAs($user)->putJson("/api/tasks/{$task->id}/tags", [
            'tag_ids' => [$tag2->id, $tag3->id],
        ]);

        $response->assertOk();
        $this->assertDatabaseMissing('tag_task', ['task_id' => $task->id, 'tag_id' => $tag1->id]);
        $this->assertDatabaseHas('tag_task', ['task_id' => $task->id, 'tag_id' => $tag2->id]);
        $this->assertDatabaseHas('tag_task', ['task_id' => $task->id, 'tag_id' => $tag3->id]);
        expect($task->tags()->count())->toBe(2);
    });

    test('an empty array removes all tags', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();
        $tag = $user->tags()->create(['name' => 'Urgent', 'color' => '#EC4899']);
        $task->tags()->attach($tag);

        $response = $this->actingAs($user)->putJson("/api/tasks/{$task->id}/tags", [
            'tag_ids' => [],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.tags', []);
        expect($task->tags()->count())->toBe(0);
    });

    test('a missing tag_ids key is rejected', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();

        $response = $this->actingAs($user)->putJson("/api/tasks/{$task->id}/tags", []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('tag_ids');
    });

    test('accepts up to 5 tags and replaces the final set entirely', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();
        $existing = $user->tags()->create(['name' => 'Existing', 'color' => '#EC4899']);
        $task->tags()->attach($existing);

        $tags = collect(range(1, 5))->map(
            fn (int $i) => $user->tags()->create(['name' => "Tag{$i}", 'color' => '#3B82F6'])
        );

        $response = $this->actingAs($user)->putJson("/api/tasks/{$task->id}/tags", [
            'tag_ids' => $tags->pluck('id')->all(),
        ]);

        $response->assertOk();
        expect($task->tags()->count())->toBe(5);
        $this->assertDatabaseMissing('tag_task', ['task_id' => $task->id, 'tag_id' => $existing->id]);
    });

    test('rejects more than 5 tags and leaves the previous set untouched', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();
        $keep = $user->tags()->create(['name' => 'Keep', 'color' => '#EC4899']);
        $task->tags()->attach($keep);

        $tags = collect(range(1, 6))->map(
            fn (int $i) => $user->tags()->create(['name' => "Tag{$i}", 'color' => '#3B82F6'])
        );

        $response = $this->actingAs($user)->putJson("/api/tasks/{$task->id}/tags", [
            'tag_ids' => $tags->pluck('id')->all(),
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('tag_ids');
        expect($task->tags()->count())->toBe(1);
        $this->assertDatabaseHas('tag_task', ['task_id' => $task->id, 'tag_id' => $keep->id]);
    });

    test('rejects duplicate ids in the payload and leaves the previous set untouched', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();
        $keep = $user->tags()->create(['name' => 'Keep', 'color' => '#EC4899']);
        $task->tags()->attach($keep);
        $tag = $user->tags()->create(['name' => 'Duplicate', 'color' => '#3B82F6']);

        $response = $this->actingAs($user)->putJson("/api/tasks/{$task->id}/tags", [
            'tag_ids' => [$tag->id, $tag->id],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('tag_ids.1');
        expect($task->tags()->count())->toBe(1);
        $this->assertDatabaseHas('tag_task', ['task_id' => $task->id, 'tag_id' => $keep->id]);
    });

    test('rejects a non-integer id and leaves the previous set untouched', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();
        $keep = $user->tags()->create(['name' => 'Keep', 'color' => '#EC4899']);
        $task->tags()->attach($keep);

        $response = $this->actingAs($user)->putJson("/api/tasks/{$task->id}/tags", [
            'tag_ids' => ['abc'],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('tag_ids.0');
        expect($task->tags()->count())->toBe(1);
    });
});

describe('tags_changed activity', function () {
    test('adding a tag records a tags_changed with it in added and an empty removed', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();
        $tag = $user->tags()->create(['name' => 'Urgente', 'color' => '#EC4899']);

        $this->actingAs($user)->putJson("/api/tasks/{$task->id}/tags", [
            'tag_ids' => [$tag->id],
        ])->assertOk();

        $activity = TaskActivity::sole();
        expect($activity->task_id)->toBe($task->id);
        expect($activity->type)->toBe(TaskActivityType::TagsChanged);
        expect($activity->data)->toBe([
            'added' => [['id' => $tag->id, 'name' => 'Urgente', 'color' => '#EC4899']],
            'removed' => [],
        ]);
    });

    test('removing a tag records it in removed with an empty added', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();
        $tag = $user->tags()->create(['name' => 'Planejamento', 'color' => '#3B82F6']);
        $task->tags()->attach($tag);

        $this->actingAs($user)->putJson("/api/tasks/{$task->id}/tags", [
            'tag_ids' => [],
        ])->assertOk();

        $activity = TaskActivity::sole();
        expect($activity->type)->toBe(TaskActivityType::TagsChanged);
        expect($activity->data)->toBe([
            'added' => [],
            'removed' => [['id' => $tag->id, 'name' => 'Planejamento', 'color' => '#3B82F6']],
        ]);
    });

    test('adding and removing in the same sync records exactly one activity with both', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();
        $kept = $user->tags()->create(['name' => 'Kept', 'color' => '#22C55E']);
        $removed = $user->tags()->create(['name' => 'Planejamento', 'color' => '#3B82F6']);
        $added = $user->tags()->create(['name' => 'Urgente', 'color' => '#EC4899']);
        $task->tags()->attach([$kept->id, $removed->id]);

        $this->actingAs($user)->putJson("/api/tasks/{$task->id}/tags", [
            'tag_ids' => [$kept->id, $added->id],
        ])->assertOk();

        expect(TaskActivity::count())->toBe(1);

        $activity = TaskActivity::sole();
        expect($activity->type)->toBe(TaskActivityType::TagsChanged);
        expect($activity->data)->toBe([
            'added' => [['id' => $added->id, 'name' => 'Urgente', 'color' => '#EC4899']],
            'removed' => [['id' => $removed->id, 'name' => 'Planejamento', 'color' => '#3B82F6']],
        ]);
    });

    test('syncing the exact same set of tags records no activity', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();
        $tag = $user->tags()->create(['name' => 'Urgente', 'color' => '#EC4899']);
        $task->tags()->attach($tag);

        $this->actingAs($user)->putJson("/api/tasks/{$task->id}/tags", [
            'tag_ids' => [$tag->id],
        ])->assertOk();

        expect(TaskActivity::count())->toBe(0);
    });

    test('added/removed snapshots are ordered by tag id regardless of request order', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();
        $tagA = $user->tags()->create(['name' => 'Alpha', 'color' => '#EC4899']);
        $tagB = $user->tags()->create(['name' => 'Beta', 'color' => '#3B82F6']);

        // Request order deliberately reversed relative to id order.
        $this->actingAs($user)->putJson("/api/tasks/{$task->id}/tags", [
            'tag_ids' => [$tagB->id, $tagA->id],
        ])->assertOk();

        $activity = TaskActivity::sole();
        expect(array_column($activity->data['added'], 'id'))->toBe([$tagA->id, $tagB->id]);
    });
});

describe('sync security', function () {
    test('rejects a non-existent tag id with a generic error and leaves the previous set untouched', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();
        $keep = $user->tags()->create(['name' => 'Keep', 'color' => '#EC4899']);
        $task->tags()->attach($keep);

        $response = $this->actingAs($user)->putJson("/api/tasks/{$task->id}/tags", [
            'tag_ids' => [999999],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('tag_ids');
        expect($task->tags()->count())->toBe(1);
    });

    test('rejects another user\'s tag id with the same generic error, revealing nothing about it', function () {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $project = Project::factory()->for($userA)->create();
        $task = Task::factory()->for($project)->create();
        $keep = $userA->tags()->create(['name' => 'Keep', 'color' => '#EC4899']);
        $task->tags()->attach($keep);
        $foreignTag = $userB->tags()->create(['name' => 'Secret tag', 'color' => '#3B82F6']);

        $response = $this->actingAs($userA)->putJson("/api/tasks/{$task->id}/tags", [
            'tag_ids' => [$foreignTag->id],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('tag_ids');
        expect($response->getContent())->not->toContain('Secret tag');
        expect($task->tags()->count())->toBe(1);
    });

    test('a non-existent tag id and another user\'s tag id produce the same error shape', function () {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $projectA = Project::factory()->for($userA)->create();
        $taskForMissing = Task::factory()->for($projectA)->create();
        $taskForForeign = Task::factory()->for($projectA)->create();
        $foreignTag = $userB->tags()->create(['name' => 'Foreign', 'color' => '#3B82F6']);

        $missingResponse = $this->actingAs($userA)->putJson("/api/tasks/{$taskForMissing->id}/tags", [
            'tag_ids' => [999999],
        ]);

        $foreignResponse = $this->actingAs($userA)->putJson("/api/tasks/{$taskForForeign->id}/tags", [
            'tag_ids' => [$foreignTag->id],
        ]);

        $missingResponse->assertUnprocessable();
        $foreignResponse->assertUnprocessable();
        expect($missingResponse->json('errors'))->toHaveKey('tag_ids');
        expect($foreignResponse->json('errors'))->toHaveKey('tag_ids');
        expect($missingResponse->json('errors.tag_ids'))->toBe($foreignResponse->json('errors.tag_ids'));
    });
});

describe('task authorization', function () {
    test('a stranger cannot sync tags on another user\'s task, even with their own valid tag ids', function () {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $task = Task::factory()->for($project)->create();
        $keep = $owner->tags()->create(['name' => 'Keep', 'color' => '#EC4899']);
        $task->tags()->attach($keep);
        $strangerTag = $stranger->tags()->create(['name' => 'Mine', 'color' => '#3B82F6']);

        $response = $this->actingAs($stranger)->putJson("/api/tasks/{$task->id}/tags", [
            'tag_ids' => [$strangerTag->id],
        ]);

        $response->assertForbidden();
        expect($task->tags()->count())->toBe(1);
        $this->assertDatabaseHas('tag_task', ['task_id' => $task->id, 'tag_id' => $keep->id]);
    });

    test('a missing task returns 404', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/tasks/999999/tags', ['tag_ids' => []])
            ->assertNotFound();
    });
});

describe('sync response ordering', function () {
    test('orders the returned tags by normalized_name, regardless of payload/attach order', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();
        $charlie = $user->tags()->create(['name' => 'Charlie', 'color' => '#EC4899']);
        $alpha = $user->tags()->create(['name' => 'alpha', 'color' => '#3B82F6']);
        $bravo = $user->tags()->create(['name' => 'Bravo', 'color' => '#22C55E']);

        $response = $this->actingAs($user)->putJson("/api/tasks/{$task->id}/tags", [
            'tag_ids' => [$charlie->id, $alpha->id, $bravo->id],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.tags.0.id', $alpha->id);
        $response->assertJsonPath('data.tags.1.id', $bravo->id);
        $response->assertJsonPath('data.tags.2.id', $charlie->id);
    });
});

describe('sync response attachments_count', function () {
    test('syncing tags does not disturb the task\'s attachments_count', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();
        Attachment::factory()->for($task)->count(2)->create();
        $tag = $user->tags()->create(['name' => 'Urgent', 'color' => '#EC4899']);

        $response = $this->actingAs($user)->putJson("/api/tasks/{$task->id}/tags", [
            'tag_ids' => [$tag->id],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.attachments_count', 2);
    });
});
