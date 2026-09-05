<?php

namespace App\Domain\Cms;

use App\Models\Page;
use App\Models\StoreSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class HomepageSelection
{
    public const KEY = 'storefront_homepage';

    public function id(): ?string
    {
        $id = data_get(StoreSetting::where('key', self::KEY)->value('value'), 'pageId');
        return $id ? (string) $id : null;
    }

    public function lock(): StoreSetting
    {
        DB::table('store_settings')->insertOrIgnore(['key' => self::KEY, 'value' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        return StoreSetting::where('key', self::KEY)->lockForUpdate()->firstOrFail();
    }

    public function assertCanRetire(Page $page): void
    {
        $selection = $this->lock();
        if ((string) data_get($selection->value, 'pageId') === (string) $page->id) {
            throw ValidationException::withMessages(['homepage' => 'Select another homepage before deleting, archiving or unpublishing the active homepage.']);
        }
    }

    public function select(Page $page, int $version): Page
    {
        return DB::transaction(function () use ($page, $version) {
            $selection = $this->lock();
            $page = Page::lockForUpdate()->findOrFail($page->id);
            $draft = $page->draftDocument()->lockForUpdate()->firstOrFail();
            if ($draft->lock_version !== $version) {
                throw Exceptions\CmsVersionConflict::for($draft->lock_version);
            }
            if ($page->status !== 'published' || !$page->publishedDocument()->exists()) {
                $page = app(PagePublicationService::class)->publish($page, $version);
            }
            $selection->update(['value' => ['pageId' => (string) $page->id]]);
            return $page->fresh()->load(['draftDocument', 'publishedDocument']);
        }, 3);
    }
}
