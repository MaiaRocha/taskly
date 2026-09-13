<?php

namespace App\Models;

use App\Enums\TaskActivityType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// task_id stays out of Fillable — it is always set by the Task relationship
// (`$task->activities()->create(...)`), never from a payload. An Activity
// is immutable by contract (no update/delete is exposed anywhere): disabling
// UPDATED_AT is the idiomatic way to keep created_at while dropping a column
// that would never legitimately change.
#[Fillable(['type', 'data'])]
class TaskActivity extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TaskActivityType::class,
            'data' => 'array',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
