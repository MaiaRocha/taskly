<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable(['title', 'short_description', 'description', 'status', 'due_at', 'position'])]
class Task extends Model
{
    use HasFactory;

    /**
     * Keep completed_at in sync with status. Only a saved Eloquent instance
     * passes through here — a mass update via the query builder
     * (Task::where(...)->update([...])) bypasses this entirely, since
     * Eloquent does not fire model events for mass updates. Any future
     * status-change operation that relies on this rule must load the model
     * and call save()/update() on the instance, not the query builder.
     */
    protected static function booted(): void
    {
        static::saving(function (Task $task): void {
            if (! $task->isDirty('status')) {
                return;
            }

            if ($task->status === TaskStatus::Completed) {
                $task->completed_at ??= now();
            } else {
                $task->completed_at = null;
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    /**
     * A plain 'datetime' cast does not normalize an offset-bearing input
     * (e.g. "2026-12-15T18:00:00-03:00") to UTC before storing it — it
     * persists the wall-clock digits of whatever timezone the input carried.
     * This mutator converts to UTC on the way in and interprets the raw,
     * offset-less DATETIME column value as UTC on the way out, instead of
     * relying on the app's default timezone to do it implicitly.
     */
    protected function dueAt(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value !== null ? Carbon::parse($value, 'UTC') : null,
            set: fn (mixed $value) => match (true) {
                $value === null => null,
                $value instanceof \DateTimeInterface => Carbon::instance($value)->utc(),
                default => Carbon::parse($value)->utc(),
            },
        );
    }

    protected function overdue(): Attribute
    {
        return Attribute::get(fn (): bool => $this->due_at !== null
            && $this->due_at->isPast()
            && $this->status !== TaskStatus::Completed
            && $this->status !== TaskStatus::Cancelled);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)
            ->orderBy('tags.normalized_name')
            ->orderBy('tags.id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    /**
     * No ordering baked in here, deliberately — the timeline read (newest
     * first) is a presentation concern for whichever query needs it, not a
     * rule of the relationship itself.
     */
    public function activities(): HasMany
    {
        return $this->hasMany(TaskActivity::class);
    }
}
