<?php

namespace App\Http\Controllers;

use App\Actions\DeleteTask;
use App\Actions\RecordTaskActivity;
use App\Enums\TaskActivityType;
use App\Enums\TaskStatus;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
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

    public function store(StoreTaskRequest $request, Project $project, RecordTaskActivity $recordTaskActivity): JsonResponse
    {
        $attributes = $request->validated();

        $attributes['status'] ??= TaskStatus::NotStarted;

        $position = ($project->tasks()->max('position') ?? -1) + 1;

        // Task creation and its task_created activity must agree: if the
        // insert fails, no activity is recorded; if recording the activity
        // fails, the task itself must not end up created either.
        $task = DB::transaction(function () use ($project, $attributes, $position, $recordTaskActivity) {
            $task = $project->tasks()->create([
                ...$attributes,
                'position' => $position,
            ]);

            $recordTaskActivity($task, TaskActivityType::TaskCreated);

            return $task;
        });

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

    public function update(UpdateTaskRequest $request, Task $task, RecordTaskActivity $recordTaskActivity): TaskResource
    {
        // Captured explicitly BEFORE the update, rather than relying on
        // wasChanged()/getOriginal() — their exact interaction with this
        // model's custom enum/Carbon-UTC casts was not verified, and an
        // explicit before/after comparison is simpler and unambiguous.
        $originalStatus = $task->status;
        $originalDueAt = $task->due_at;

        DB::transaction(function () use ($request, $task, $originalStatus, $originalDueAt, $recordTaskActivity) {
            $task->update($request->validated());

            // Deterministic order: status_changed before due_at_changed,
            // regardless of key order in the request payload.
            if ($task->status !== $originalStatus) {
                $recordTaskActivity($task, TaskActivityType::StatusChanged, [
                    'from' => $originalStatus->value,
                    'to' => $task->status->value,
                ]);
            }

            $originalDueAtIso = $originalDueAt?->toIso8601ZuluString();
            $newDueAtIso = $task->due_at?->toIso8601ZuluString();

            if ($originalDueAtIso !== $newDueAtIso) {
                $recordTaskActivity($task, TaskActivityType::DueAtChanged, [
                    'from' => $originalDueAtIso,
                    'to' => $newDueAtIso,
                ]);
            }
        });

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
