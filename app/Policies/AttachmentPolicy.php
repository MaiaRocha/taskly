<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\Task;
use App\Models\User;

class AttachmentPolicy
{
    public function view(User $user, Attachment $attachment): bool
    {
        return $attachment->task->project->user_id === $user->id;
    }

    public function create(User $user, Task $task): bool
    {
        return $task->project->user_id === $user->id;
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        return $attachment->task->project->user_id === $user->id;
    }
}
