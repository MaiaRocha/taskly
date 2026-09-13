<?php

namespace App\Http\Controllers;

use App\Actions\RecordTaskActivity;
use App\Actions\StoreAttachments;
use App\Enums\TaskActivityType;
use App\Http\Requests\StoreAttachmentsRequest;
use App\Http\Resources\AttachmentResource;
use App\Models\Attachment;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AttachmentController extends Controller
{
    /**
     * MIME types the browser can render directly. Everything else (Office
     * formats) is forced as a download, since the browser cannot display it.
     *
     * @var list<string>
     */
    private const INLINE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
        'text/plain',
    ];

    public function index(Task $task): AnonymousResourceCollection
    {
        Gate::authorize('view', $task);

        $attachments = $task->attachments()
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return AttachmentResource::collection($attachments);
    }

    public function store(StoreAttachmentsRequest $request, Task $task, StoreAttachments $storeAttachments): JsonResponse
    {
        $attachments = $storeAttachments($task, $request->file('files'));

        return AttachmentResource::collection($attachments)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function download(Attachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $attachment);

        $disk = Storage::disk(config('filesystems.attachments_disk'));

        if (! $disk->exists($attachment->path)) {
            throw new RuntimeException('Attachment file is missing from storage.');
        }

        $disposition = in_array($attachment->mime_type, self::INLINE_MIME_TYPES, true)
            ? 'inline'
            : 'attachment';

        return $disk->response(
            $attachment->path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type],
            $disposition,
        );
    }

    public function destroy(Attachment $attachment, RecordTaskActivity $recordTaskActivity): Response
    {
        Gate::authorize('delete', $attachment);

        // The database is the source of truth: the row delete and its
        // activity commit FIRST, atomically. Only once that has actually
        // succeeded do we attempt the physical file cleanup — reusing the
        // same disk/path captured now, since $attachment's attributes stay
        // readable in memory even after ->delete(). This ordering means a
        // DB failure leaves row, activity, AND file all untouched (rolled
        // back together), and a physical-cleanup failure after a successful
        // commit leaves, at worst, a private orphaned file — never a row
        // pointing at a file that's already gone.
        $task = $attachment->task;
        $originalName = $attachment->original_name;
        $path = $attachment->path;

        DB::transaction(function () use ($attachment, $task, $originalName, $recordTaskActivity) {
            $attachment->delete();
            $recordTaskActivity($task, TaskActivityType::AttachmentRemoved, ['name' => $originalName]);
        });

        $disk = Storage::disk(config('filesystems.attachments_disk'));

        try {
            if ($disk->exists($path) && ! $disk->delete($path) && $disk->exists($path)) {
                Log::warning('Failed to delete an attachment file after its row was removed.', [
                    'attachment_original_name' => $originalName,
                    'task_id' => $task->id,
                    'path' => $path,
                ]);
            }
        } catch (Throwable $e) {
            // The DB half already committed — the delete is logically done
            // from the client's point of view. Never turn a filesystem
            // hiccup into a failed response for a row that's already gone.
            Log::warning('Unexpected error while deleting an attachment file after its row was removed.', [
                'attachment_original_name' => $originalName,
                'task_id' => $task->id,
                'path' => $path,
                'exception' => $e::class,
            ]);
        }

        return response()->noContent();
    }
}
