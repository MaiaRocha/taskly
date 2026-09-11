<?php

namespace App\Models;

use App\Support\ColorPalette;
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
     * Kept for backward compatibility with existing call sites; the palette
     * itself lives in ColorPalette so Project and Tag share one source.
     *
     * @var list<string>
     */
    public const array COLORS = ColorPalette::AUXILIARY;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
