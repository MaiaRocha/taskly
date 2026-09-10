<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// No #[Fillable] yet: original_name/path/mime_type/size are internal file
// metadata the application will populate from the upload pipeline (Phase 7),
// and task_id is ownership-linked, not client input. Every attribute stays
// guarded until that phase defines what is actually safe to mass-assign.
class Attachment extends Model
{
    use HasFactory;

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
