<?php

namespace App\Http\Controllers;

use App\Http\Requests\SyncTaskTagsRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Support\Facades\DB;

class TaskTagController extends Controller
{
    public function update(SyncTaskTagsRequest $request, Task $task): TaskResource
    {
        $tagIds = $request->validated('tag_ids');

        DB::transaction(function () use ($task, $tagIds) {
            $task->tags()->sync($tagIds);
        });

        $task->load('tags');

        return new TaskResource($task);
    }
}
