<?php

namespace App\Http\Controllers;

use App\Http\Resources\TaskActivityResource;
use App\Models\Task;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TaskActivityController extends Controller
{
    /**
     * Read-only timeline for a Task — no store/update/destroy exist.
     * Activities are only ever created as a side effect of real Task
     * mutations (see RecordTaskActivity and its callers), never through
     * this endpoint.
     */
    public function index(Task $task): AnonymousResourceCollection
    {
        Gate::authorize('view', $task);

        $activities = $task->activities()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20);

        return TaskActivityResource::collection($activities);
    }
}
