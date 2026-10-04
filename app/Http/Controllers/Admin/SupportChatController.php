<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportConversation;
use App\Services\Support\SupportChatService;
use Illuminate\Http\Request;

class SupportChatController extends Controller
{
    public function archive(Request $request, SupportConversation $conversation, SupportChatService $chat)
    {
        $data=$request->validate(['archived'=>['required','boolean']]);
        \Illuminate\Support\Facades\DB::transaction(function() use($conversation,$request,$data){
            $locked=SupportConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            $locked->notes()->create(['staff_user_id'=>$request->user()->id,'content'=>'[Archive] '.($data['archived']?'true':'false')]);
            $locked->touch();
        });
        return response()->json(['conversation'=>$chat->adminView($conversation->fresh())]);
    }
    public function index(Request $request, SupportChatService $chat)
    {
        $data = $request->validate(['since' => ['nullable', 'date']]);
        $syncedAt = now()->toIso8601String();
        $query = SupportConversation::with(['customerUser.customer', 'messages.staff', 'notes.staff']);
        if (!empty($data['since'])) $query->where('updated_at', '>=', \Illuminate\Support\Carbon::parse($data['since']));
        return response()->json(['syncedAt' => $syncedAt, 'conversations' => $query
            ->orderByDesc('last_message_at')->get()->map(fn ($c) => $chat->adminView($c))->all()])->header('Cache-Control', 'no-store');
    }
    public function send(Request $request, SupportConversation $conversation, SupportChatService $chat)
    {
        abort_if($conversation->channel==='facebook',409,'Messenger receiving is enabled. Sending replies is not enabled yet.');
        $data = $request->validate(['content' => ['required', 'string', 'max:2000'], 'client_message_id' => ['required', 'uuid']]);
        $data['content'] = trim($data['content']);
        abort_if($data['content'] === '', 422, 'Message cannot be empty.');
        return response()->json(['conversation' => $chat->adminView($chat->append($conversation, $data, 'human_agent', $request->user()->id))]);
    }
    public function status(Request $request, SupportConversation $conversation, SupportChatService $chat)
    {
        $data = $request->validate(['status' => ['required', 'in:open,human_mode,resolved']]);
        if($conversation->channel==='facebook'&&$data['status']==='open'){
            abort_unless($conversation->status==='resolved',409,'AI replies are not enabled for Messenger.');
            $data['status']='waiting_human';
        }
        \Illuminate\Support\Facades\DB::transaction(function () use ($conversation, $data, $request) {
            $locked = SupportConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== $data['status']) {
                $locked->notes()->create(['staff_user_id' => $request->user()->id, 'content' => '[Control] '.$locked->status.' → '.$data['status']]);
                $locked->update($data);
            }
        });
        return response()->json(['conversation' => $chat->adminView($conversation->fresh())]);
    }
    public function read(Request $request, SupportConversation $conversation, SupportChatService $chat)
    {
        $data = $request->validate(['through_message_id' => ['required', 'integer', 'min:0']]);
        $chat->read($conversation, $data['through_message_id']);
        return response()->json(['conversation' => $chat->adminView($conversation->fresh())]);
    }
    public function note(Request $request, SupportConversation $conversation, SupportChatService $chat)
    {
        $data = $request->validate(['content' => ['required', 'string', 'max:2000']]);
        $data['content'] = trim($data['content']);
        abort_if($data['content'] === '', 422, 'Note cannot be empty.');
        $conversation->notes()->create(['content' => $data['content'], 'staff_user_id' => $request->user()->id]);
        $conversation->touch();
        return response()->json(['conversation' => $chat->adminView($conversation->fresh())]);
    }
}
