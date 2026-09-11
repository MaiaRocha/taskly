<?php

namespace App\Http\Controllers;

use App\Actions\DeleteTask;
use App\Enums\TaskStatus;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class TaskController extends Controller
{
    public function index(Project $project): AnonymousResourceCollection
    {
        Gate::authorize('view', $project);

        $tasks = $project->tasks()
            ->with('tags')
            ->withCount('attachments')
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        return TaskResource::collection($tasks);
    }

    public function store(StoreTaskRequest $request, Project $project): JsonResponse
    {
        $attributes = $request->validated();

        $attributes['status'] ??= TaskStatus::NotStarted;

        $position = ($project->tasks()->max('position') ?? -1) + 1;

        $task = $project->tasks()->create([
            ...$attributes,
            'position' => $position,
        ]);

        $task->load('tags');
        $task->loadCount('attachments');

        return (new TaskResource($task))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Task $task): TaskResource
    {
        Gate::authorize('view', $task);

        $task->load('tags');
        $task->loadCount('attachments');

        return new TaskResource($task);
    }

    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        $task->update($request->validated());

        $task->load('tags');
        $task->loadCount('attachments');

        return new TaskResource($task);
    }

    public function destroy(Task $task, DeleteTask $deleteTask): Response
    {
        Gate::authorize('delete', $task);

        $deleteTask($task);

        return response()->noContent();
    }
}
