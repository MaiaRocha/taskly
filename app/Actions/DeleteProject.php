<?php

namespace App\Actions;

use App\Models\Project;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DeleteProject
{
    /**
     * Delete the project, then best-effort clean up every one of its tasks'
     * attachments directories. Task IDs are collected before the delete
     * (the cascade removes the rows we'd otherwise query for), and the
     * database delete still happens before any filesystem work. A failure
     * cleaning up one task's directory never stops the rest — each is
     * independent and reported on its own.
     */
    public function __invoke(Project $project): void
    {
        $projectId = $project->id;
        $taskIds = $project->tasks()->pluck('id');

        $project->delete();

        $disk = config('filesystems.attachments_disk');

        foreach ($taskIds as $taskId) {
            $this->cleanupAttachmentsDirectory($disk, $projectId, $taskId);
        }
    }

    private function cleanupAttachmentsDirectory(string $disk, int $projectId, int $taskId): void
    {
        $directory = "attachments/{$taskId}";

        try {
            $storage = Storage::disk($disk);

            if (! $storage->directoryExists($directory)) {
                return;
            }

            if (! $storage->deleteDirectory($directory) && $storage->directoryExists($directory)) {
                Log::warning('Failed to clean up attachments directory after project deletion.', [
                    'project_id' => $projectId,
                    'task_id' => $taskId,
                    'disk' => $disk,
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('Unexpected error while cleaning up attachments directory after project deletion.', [
                'project_id' => $projectId,
                'task_id' => $taskId,
                'disk' => $disk,
                'exception' => $e::class,
            ]);
        }
    }
}
