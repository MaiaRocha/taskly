<?php

namespace App\Http\Controllers;

use App\Actions\StoreAttachments;
use App\Http\Requests\StoreAttachmentsRequest;
use App\Http\Resources\AttachmentResource;
use App\Models\Attachment;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function destroy(Attachment $attachment): Response
    {
        Gate::authorize('delete', $attachment);

        $disk = Storage::disk(config('filesystems.attachments_disk'));

        if ($disk->exists($attachment->path)) {
            $deleted = $disk->delete($attachment->path);

            if (! $deleted && $disk->exists($attachment->path)) {
                throw new RuntimeException('Unable to delete attachment file.');
            }
        }

        $attachment->delete();

        return response()->noContent();
    }
}
