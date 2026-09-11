<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'color', 'position'])]
class Project extends Model
{
    use HasFactory;

    /**
     * The auxiliary colors approved for projects/tags/indicators (docs/UI-UX.md §5).
     * Does not include the Primary brand/action color (#635BFF).
     *
     * @var list<string>
     */
    public const array COLORS = [
        '#06B6D4',
        '#14B8A6',
        '#EC4899',
        '#F59E0B',
        '#22C55E',
        '#3B82F6',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
