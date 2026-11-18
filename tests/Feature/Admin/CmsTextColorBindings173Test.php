<?php

namespace Tests\Feature\Admin;

use App\Domain\Cms\PageRuntimePayloadFactory;
use App\Models\Page;
use App\Models\User;
use App\Models\CmsMediaAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsTextColorBindings173Test extends TestCase
{
    use RefreshDatabase;

    public function test_all_registered_starters_keep_scoped_color_bindings_through_canonical_save_reload_and_runtime(): void
    {
        $this->assertSame('sqlite',config('database.default'));
        $this->assertSame(':memory:',config('database.connections.sqlite.database'));
        $dir = base_path('output/text-color-bindings-20261118/canonical');
        // The actual JS wire format contains empty JSON objects, not empty arrays.
        $native = function ($value) use (&$native) {
            if ($value instanceof \stdClass) {
                $properties = get_object_vars($value);
                return $properties === [] ? new \stdClass : array_map($native, $properties);
            }
            return is_array($value) ? array_map($native, $value) : $value;
        };
        if (! is_dir($dir)) mkdir($dir, 0777, true);
        // Bundled insertion fixtures keep this test runnable independently of generated UI audit output.
        $sources = $native(json_decode(file_get_contents(base_path('tests/Fixtures/Cms/unified-section-defaults-v1/documents-173.json')), flags: JSON_THROW_ON_ERROR));
        $rows = [];
        foreach ($sources as $index => $source) {
            $original = $source['sections'][0];
            $original['id'] = 'alignment-'.$index;
            $edits = [];
            foreach (['auto','dark','light','custom'] as $value) {
                $binding = ['source'=>$value];
                if ($value==='custom') $binding['color']='#8a2244';
                $target=$original['items'][0]['id'] ?? '$section';
                $role=$target==='$section'?'title':'itemTitle';
                $edits[]=['section'=>array_merge($original,['textColorBindings'=>[$target=>[$role=>$binding]]])];
            }
            $edits[]=['section'=>$original];
            $rows[] = ['original'=>$original, 'edits'=>$edits];
        }
        $this->assertCount(173, $rows);
        // Mirror referenced managed-media identities into the isolated test DB.
        // This tests layout transport, without deleting/replacing the original image URLs.
        preg_match_all('~"(/storage/cms/media/[^"\\\\]+)"~', json_encode($rows), $matches);
        foreach (array_unique($matches[1]) as $url) {
            $path = substr($url, strlen('/storage/'));
            CmsMediaAsset::query()->create(['id' => (string) \Illuminate\Support\Str::uuid(), 'disk' => 'public', 'path' => $path, 'url' => $url, 'original_name' => basename($path), 'mime_type' => 'image/png', 'size' => 1, 'width' => 1, 'height' => 1, 'checksum' => hash('sha256', $url)]);
        }
        $document = ['title' => 'All173 layout controls QA', 'slug' => 'all173-layout-controls-test', 'template' => 'standard', 'sections' => array_column($rows, 'original')];
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
        // Hidden legacy values are retained rather than migrated or pruned.
        $manifest = json_decode(file_get_contents(resource_path('cms/page-contract.v1.json')), true, flags: JSON_THROW_ON_ERROR);
        $document['sections'] = array_map(function ($row) use ($manifest) {
            $section = array_merge($row['original'], ['paddingY'=>'spacious','textAlignment'=>'right']);
            $allowed = $manifest['section']['sectionTypes'][$section['type']]['fields'];
            // Retain valid old fields; do not invent values forbidden even before this repair.
            foreach (['paddingY', 'textAlignment'] as $field) {
                if (! in_array($field, $allowed, true)) unset($section[$field]);
            }
            return $section;
        }, $rows);
        $this->patchJson(route('admin.api.pages.draft.update', $id), ['lockVersion' => $lock++, 'document' => $document])->assertOk();
        $verify('retained-legacy-values');
        $document['sections'] = array_column($rows, 'original');
        $this->patchJson(route('admin.api.pages.draft.update', $id), ['lockVersion' => $lock, 'document' => $document])->assertOk();
        $verify('restored-original');
        $blocked=[];
        foreach ($document['sections'] as $i=>$section) {
            if(in_array('textAlignment',$manifest['section']['sectionTypes'][$section['type']]['fields'],true))continue;
            $trial=$document;$trial['sections'][$i]['textAlignment']='left';
            $this->patchJson(route('admin.api.pages.draft.update',$id),['lockVersion'=>$lock+1,'document'=>$trial])->assertUnprocessable();
            $blocked[]=['number'=>$i+1,'type'=>$section['type']];
        }
        file_put_contents($dir.'/blocked-contracts.json',json_encode($blocked,JSON_PRETTY_PRINT));
        file_put_contents($dir.'/api-reloaded.json', json_encode(['sections' => 173, 'published' => false, 'receipts' => $receipts], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
    public function test_rejects_unknown_sources_roles_invalid_colors_and_unsafe_target_keys(): void
    {
        $doc=['title'=>'QA','slug'=>'qa-colors','template'=>'standard','sections'=>[['id'=>'s','type'=>'feature_cards','title'=>'Cards','isVisible'=>true]]];
        foreach ([['a'=>['itemTitle'=>['source'=>'custom']]],['a'=>['itemTitle'=>['source'=>'light','color'=>'#ffffff']]],['a'=>['wrongRole'=>['source'=>'auto']]],['a'=>['itemTitle'=>['source'=>'bad']]],['a"{}'=>['itemTitle'=>['source'=>'auto']]]] as $invalid) {
            $trial=$doc;$trial['sections'][0]['textColorBindings']=$invalid;
            try {app(\App\Domain\Cms\PageDocumentContract::class)->validate($trial);$this->fail('Malformed binding accepted');}
            catch (\Illuminate\Validation\ValidationException $e) {$this->assertNotEmpty($e->errors());}
        }
    }

}
