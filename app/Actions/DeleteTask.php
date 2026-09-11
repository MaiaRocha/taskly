<?php

namespace App\Actions;

use App\Models\Task;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DeleteTask
{
    /**
     * Delete the task, then best-effort clean up its attachments directory.
     *
     * The database delete happens first: if it fails, the task, its rows,
     * and its files all stay consistent. Only once the task (and its
     * cascaded attachment/tag_task rows) are confirmed gone do we attempt
     * the physical cleanup — which is deliberately observable-but-silent:
     * a leftover directory is logged, never turned into a failed response
     * for a delete that already succeeded from the user's point of view.
     */
    public function __invoke(Task $task): void
    {
        $taskId = $task->id;

        $task->delete();

        $this->cleanupAttachmentsDirectory($taskId);
    }

    private function cleanupAttachmentsDirectory(int $taskId): void
    {
        $disk = config('filesystems.attachments_disk');
        $directory = "attachments/{$taskId}";

        try {
            $storage = Storage::disk($disk);

            if (! $storage->directoryExists($directory)) {
                return;
            }

            if (! $storage->deleteDirectory($directory) && $storage->directoryExists($directory)) {
                Log::warning('Failed to clean up attachments directory after task deletion.', [
                    'task_id' => $taskId,
                    'disk' => $disk,
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('Unexpected error while cleaning up attachments directory after task deletion.', [
                'task_id' => $taskId,
                'disk' => $disk,
                'exception' => $e::class,
            ]);
        }
    }
}
