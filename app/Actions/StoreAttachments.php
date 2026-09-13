<?php

namespace App\Actions;

use App\Enums\TaskActivityType;
use App\Models\Attachment;
use App\Models\Task;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class StoreAttachments
{
    public function __construct(private readonly RecordTaskActivity $recordTaskActivity) {}

    /**
     * Store the given uploaded files on the task, as a single all-or-nothing
     * batch: either every file is written and persisted, or none is.
     *
     * @param  array<int, UploadedFile>  $files
     * @return Collection<int, Attachment>
     */
    public function __invoke(Task $task, array $files): Collection
    {
        $disk = config('filesystems.attachments_disk');
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($task, $files, $disk, &$storedPaths) {
                $lockedTask = Task::whereKey($task->id)->lockForUpdate()->firstOrFail();

                if ($lockedTask->attachments()->count() + count($files) > 10) {
                    throw ValidationException::withMessages([
                        'files' => 'This task cannot have more than 10 attachments.',
                    ]);
                }

                $attachments = collect($files)->map(function (UploadedFile $file) use ($lockedTask, $disk, &$storedPaths) {
                    $path = $file->store("attachments/{$lockedTask->id}", $disk);

                    if ($path === false) {
                        throw new RuntimeException('Failed to store an uploaded attachment.');
                    }

                    $storedPaths[] = $path;

                    return $lockedTask->attachments()->create([
                        'original_name' => $this->displayName($file),
                        'path' => $path,
                        'mime_type' => $file->getMimeType(),
                        'size' => $file->getSize(),
                    ]);
                });

                // One grouped activity for the whole batch — never one per file.
                ($this->recordTaskActivity)($lockedTask, TaskActivityType::AttachmentsAdded, [
                    'files' => $attachments->map(fn (Attachment $attachment) => ['name' => $attachment->original_name])->all(),
                ]);

                return $attachments;
            });
        } catch (Throwable $e) {
            if ($storedPaths !== []) {
                Storage::disk($disk)->delete($storedPaths);
            }

            throw $e;
        }
    }

    /**
     * The client-supplied filename, kept purely as a display value: never
     * used to build the physical path. Strips any path component a hostile
     * client might smuggle in and caps the length to fit the column.
     */
    private function displayName(UploadedFile $file): string
    {
        $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));

        return Str::limit($name, 255, '');
    }
}
