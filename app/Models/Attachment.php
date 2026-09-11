<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// original_name/path/mime_type/size are server-derived from the uploaded
// file, never trusted from the client payload directly, so they are safe to
// mass-assign. task_id stays out of Fillable — it is set by the Task
// relationship (`$task->attachments()->create(...)`), never from a payload.
#[Fillable(['original_name', 'path', 'mime_type', 'size'])]
class Attachment extends Model
{
    use HasFactory;

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
