<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportFaqDraft;
use App\Services\Support\SupportImprovementService;
use App\Services\SupportFaqService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupportImprovementController extends Controller
{
    private function authorizeRequest(Request $r): void
    {
        abort_unless($r->user()?->is_super_admin, 403);
    }

    public function index(Request $r, SupportImprovementService $s)
    {
        $this->authorizeRequest($r);
        $data = $r->validate(['days' => ['sometimes', 'integer', Rule::in([7, 30])], 'channel' => ['sometimes', Rule::in(['all', 'storefront', 'facebook', 'instagram', 'whatsapp', 'tiktok'])]]);

        return response()->json($s->overview((int) ($data['days'] ?? 7), $data['channel'] ?? 'all'))->header('Cache-Control', 'no-store');
    }

    public function drafts(Request $r, SupportImprovementService $s, SupportFaqService $faq)
    {
        $this->authorizeRequest($r);
        $s->activatedAt();
        $data = $r->validate(['before' => 'sometimes|integer|min:1']);
        $rows = SupportFaqDraft::with(['sourceMessage', 'staffMessage', 'conversation.customerUser', 'conversation.customer'])->when(isset($data['before']), fn ($q) => $q->where('id', '<', $data['before']))->latest('id')->limit(201)->get();

        return response()->json(['drafts' => $rows->take(200)->map(fn ($d) => $s->view($d))->values(), 'limited' => $rows->count() > 200, 'nextCursor' => $rows->count() > 200 ? $rows->take(200)->last()->id : null, 'pending' => SupportFaqDraft::where('status', 'Needs review')->count(), 'categories' => $faq->read()['faq']['categories'] ?? [['id' => 'others', 'label' => 'Others']]])->header('Cache-Control', 'no-store');
    }

    public function store(Request $r, SupportImprovementService $s)
    {
        $this->authorizeRequest($r);
        $data = $r->validate(['sourceMessageId' => 'required|integer|min:1']);
        [$draft,$created] = $s->create($data['sourceMessageId'], $r->user());

        return response()->json(['draft' => $s->view($draft)], $created ? 201 : 200)->header('Cache-Control', 'no-store');
    }

    public function update(Request $r, SupportFaqDraft $draft, SupportImprovementService $s)
    {
        $this->authorizeRequest($r);
        $data = $r->validate(['version' => 'required|integer|min:1', 'question' => 'required|string|max:1000', 'answer' => 'present|nullable|string|max:10000', 'category' => 'required|string|max:100']);
        $data['answer'] = $data['answer'] ?? '';

        return response()->json(['draft' => $s->view($s->edit($draft, $data))])->header('Cache-Control', 'no-store');
    }

    public function publish(Request $r, SupportFaqDraft $draft, SupportImprovementService $s)
    {
        return $this->review($r, $draft, $s, true);
    }

    public function dismiss(Request $r, SupportFaqDraft $draft, SupportImprovementService $s)
    {
        return $this->review($r, $draft, $s, false);
    }

    private function review(Request $r, SupportFaqDraft $d, SupportImprovementService $s, bool $publish)
    {
        $this->authorizeRequest($r);
        $data = $r->validate(['version' => 'required|integer|min:1', 'policyConfirmed' => $publish ? 'required|accepted' : 'sometimes|boolean']);
        $draft = $s->review($d, $data['version'], $publish, $r->user());
        $settings = $publish ? app(SupportFaqService::class)->read() : null;

        return response()->json(['draft' => $s->view($draft), 'faq' => $settings ? ['version' => $settings['version'], 'scope' => $settings['faq']] : null])->header('Cache-Control','no-store');
    }
}
