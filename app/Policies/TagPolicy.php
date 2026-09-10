<?php

namespace App\Policies;

use App\Models\Tag;
use App\Models\User;

class TagPolicy
{
    public function view(User $user, Tag $tag): bool
    {
        return $tag->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Tag $tag): bool
    {
        return $tag->user_id === $user->id;
    }

    public function delete(User $user, Tag $tag): bool
    {
        return $tag->user_id === $user->id;
    }
}
