<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'color'])]
class Tag extends Model
{
    use HasFactory;

    /**
     * Single source of truth for normalized_name: trim + Unicode lowercase.
     * Reused by the name() mutator below and by StoreTagRequest/
     * UpdateTagRequest so the uniqueness check validates against exactly
     * what will be persisted, without duplicating the algorithm.
     */
    public static function normalize(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): array => [
                'name' => trim($value),
                'normalized_name' => self::normalize($value),
            ],
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class);
    }
}
