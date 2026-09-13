<?php

namespace App\Http\Controllers;

use App\Actions\RecordTaskActivity;
use App\Enums\TaskActivityType;
use App\Http\Requests\SyncTaskTagsRequest;
use App\Http\Resources\TaskResource;
use App\Models\Tag;
use App\Models\Task;
use Illuminate\Support\Facades\DB;

class TaskTagController extends Controller
{
    public function update(SyncTaskTagsRequest $request, Task $task, RecordTaskActivity $recordTaskActivity): TaskResource
    {
        $tagIds = $request->validated('tag_ids');

        DB::transaction(function () use ($task, $tagIds, $recordTaskActivity) {
            $result = $task->tags()->sync($tagIds);

            if (empty($result['attached']) && empty($result['detached'])) {
                // An identical sync (or a no-op) is not an event.
                return;
            }

            // sync() only returns ids — the snapshot needs the real
            // name/color, fetched in one query for both attached and
            // detached ids together (no N+1 across the two lists).
            $affectedIds = [...$result['attached'], ...$result['detached']];
            $tagsById = Tag::whereIn('id', $affectedIds)->get()->keyBy('id');

            // Sorted by id ascending explicitly — sync()'s own array order
            // for 'attached'/'detached' is an implementation detail, not a
            // contract, so the payload must not depend on it.
            $snapshot = fn (array $ids) => collect($ids)
                ->sort()
                ->map(fn (int $id) => $tagsById->get($id))
                ->filter()
                ->map(fn (Tag $tag) => ['id' => $tag->id, 'name' => $tag->name, 'color' => $tag->color])
                ->values()
                ->all();

            $recordTaskActivity($task, TaskActivityType::TagsChanged, [
                'added' => $snapshot($result['attached']),
                'removed' => $snapshot($result['detached']),
            ]);
        });

        $task->load('tags');
        $task->loadCount('attachments');

        return new TaskResource($task);
    }
}
