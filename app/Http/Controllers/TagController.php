<?php

namespace App\Http\Controllers;

use App\Actions\RecordTaskActivity;
use App\Enums\TaskActivityType;
use App\Http\Requests\StoreTagRequest;
use App\Http\Requests\UpdateTagRequest;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TagController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $tags = $request->user()->tags()
            ->orderBy('normalized_name')
            ->orderBy('id')
            ->get();

        return TagResource::collection($tags);
    }

    public function store(StoreTagRequest $request): JsonResponse
    {
        $tag = $request->user()->tags()->create($request->validated());

        return (new TagResource($tag))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateTagRequest $request, Tag $tag): TagResource
    {
        $tag->update($request->validated());

        return new TagResource($tag);
    }

    public function destroy(Tag $tag, RecordTaskActivity $recordTaskActivity): Response
    {
        Gate::authorize('delete', $tag);

        $snapshot = ['id' => $tag->id, 'name' => $tag->name, 'color' => $tag->color];

        DB::transaction(function () use ($tag, $snapshot, $recordTaskActivity) {
            // Read the currently-associated Tasks BEFORE deleting the Tag —
            // the pivot rows (and with them, the only record of "which Tasks
            // had this Tag") disappear via the tag_task FK cascade the
            // moment the Tag itself is deleted below.
            $affectedTasks = $tag->tasks()->get();

            foreach ($affectedTasks as $task) {
                $recordTaskActivity($task, TaskActivityType::TagsChanged, [
                    'added' => [],
                    'removed' => [$snapshot],
                ]);
            }

            // The pivot cascade (tag_task.tag_id -> cascadeOnDelete()) is
            // still what actually detaches the Tag from every Task — this
            // only makes sure each affected Task's history records it too.
            $tag->delete();
        });

        return response()->noContent();
    }
}
