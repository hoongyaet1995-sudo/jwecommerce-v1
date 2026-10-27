<?php

namespace App\Services\Marketing;

use App\Models\MarketingAvatarLibrary;
use App\Models\MarketingAvatarLibraryMember;
use App\Models\User;

class MarketingAvatarAccess
{
    public function libraries(User $actor): array
    {
        abort_unless($actor->is_admin || $actor->is_super_admin, 403);

        return array_values(array_unique(array_merge(MarketingAvatarLibrary::where('owner_id', $actor->id)->pluck('id')->all(), MarketingAvatarLibraryMember::where('user_id', $actor->id)->pluck('library_id')->all())));
    }

    public function check(User $actor, string $library, bool $write = false): MarketingAvatarLibrary
    {
        $row = MarketingAvatarLibrary::whereIn('id', $this->libraries($actor))->findOrFail($library);
        if ($write) {
            abort_unless($row->owner_id === $actor->id || MarketingAvatarLibraryMember::where('library_id', $library)->where('user_id', $actor->id)->whereIn('role', ['manager', 'editor'])->exists(), 403);
        }

        return $row;
    }
}
