<?php

namespace Tests\Feature\Admin;

use App\Domain\Cms\PageRuntimePayloadFactory;
use App\Models\Page;
use App\Models\User;
use App\Models\CmsMediaAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsActionAppearanceAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_161_text_action_appearance_edits_save_reload_and_reach_customer_runtime_without_publication(): void
    {
        $dir = base_path('docs/planning/section-passport-2026-11-13/color-buttons');
        // The actual JS wire format contains empty JSON objects, not empty arrays.
        $native = function ($value) use (&$native) {
            if ($value instanceof \stdClass) {
                $properties = get_object_vars($value);
                return $properties === [] ? new \stdClass : array_map($native, $properties);
            }
            return is_array($value) ? array_map($native, $value) : $value;
        };
        $rows = $native(json_decode(file_get_contents($dir.'/edit-fixtures.json'), flags: JSON_THROW_ON_ERROR))['rows'];
        $this->assertCount(161, $rows);
        // Mirror referenced managed-media identities into the isolated test DB.
        // This tests layout transport, without deleting/replacing the original image URLs.
        preg_match_all('~"(/storage/cms/media/[^"\\\\]+)"~', file_get_contents($dir.'/edit-fixtures.json'), $matches);
        foreach (array_unique($matches[1]) as $url) {
            $path = substr($url, strlen('/storage/'));
            CmsMediaAsset::query()->create(['id' => (string) \Illuminate\Support\Str::uuid(), 'disk' => 'public', 'path' => $path, 'url' => $url, 'original_name' => basename($path), 'mime_type' => 'image/png', 'size' => 1, 'width' => 1, 'height' => 1, 'checksum' => hash('sha256', $url)]);
        }
        $document = ['title' => 'All161 action appearance QA', 'slug' => 'all161-action-appearance-test', 'template' => 'standard', 'sections' => array_column($rows, 'original')];
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $create = $this->postJson(route('admin.api.pages.store'), $document)->assertCreated();
        $id = $create->json('data.id');
        $lock = 1;
        $receipts = [];
        $verify = function (string $state) use ($id, &$document, &$receipts): void {
            $response = $this->getJson(route('admin.api.pages.draft.show', $id))->assertOk();
            $saved = json_decode($response->getContent(), flags: JSON_THROW_ON_ERROR)->data->document;
            $this->assertEquals(json_decode(json_encode($document)), $saved, $state.' canonical reload, including empty object identity');
            $page = Page::query()->findOrFail($id);
            $runtime = app(PageRuntimePayloadFactory::class)->forDraft($page);
            $this->assertEquals($saved, json_decode(json_encode($runtime['page']['document'])), $state.' customer renderer input');
            $this->assertSame('draft', $page->status);
            $this->assertNull($page->publishedDocument);
            $receipts[] = ['state' => $state, 'document' => $saved];
        };
        $verify('original');
        $max = max(array_map(fn ($row) => count($row['edits']), $rows));
        for ($n = 0; $n < $max; $n++) {
            $document['sections'] = array_map(fn ($row) => $row['edits'][$n]['section'] ?? $row['original'], $rows);
            $this->patchJson(route('admin.api.pages.draft.update', $id), ['lockVersion' => $lock, 'document' => $document])->assertOk();
            $lock++;
            $verify('edited-option-'.$n);
        }
        $document['sections'] = array_column($rows, 'edited');
        $this->patchJson(route('admin.api.pages.draft.update', $id), ['lockVersion' => $lock++, 'document' => $document])->assertOk();
        $verify('all-overrides');
        $document['style'] = ['palette' => ['paper'=>'#f7f6f2','ink'=>'#292b24','muted'=>'#6d7267','accent'=>'#454e37','light'=>'#f7f6f2','secondary'=>'#dad7c7','secondaryInk'=>'#292b24','hover'=>'#654321'], 'buttonHover'=>true, 'unifiedButtons'=>true,'buttonRadius'=>6,'buttonSize'=>'large','typography'=>['title'=>['color'=>'#123456','colorOverride'=>true]]];
        $this->patchJson(route('admin.api.pages.draft.update', $id), ['lockVersion' => $lock++, 'document' => $document])->assertOk();
        $verify('page-and-local-overrides');
        $document['sections'] = array_column($rows, 'original');
        $document['style']['preserveLayout'] = true;
        $document['style']['unifiedButtons'] = false;
        $document['style']['buttonHoverColor'] = '#8a00cc';
        unset($document['style']['typography']);
        $this->patchJson(route('admin.api.pages.draft.update', $id), ['lockVersion' => $lock++, 'document' => $document])->assertOk();
        $verify('appearance-only');
        unset($document['style']);
        $document['sections'] = array_column($rows, 'original');
        $this->patchJson(route('admin.api.pages.draft.update', $id), ['lockVersion' => $lock, 'document' => $document])->assertOk();
        $verify('restored-original');
        file_put_contents($dir.'/api-reloaded.json', json_encode(['sections' => 161, 'published' => false, 'receipts' => $receipts], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
