<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Support\FaqAutoReplyService;
use App\Services\Support\SupportChatService;
use Illuminate\Http\Request;

class SupportChatController extends Controller
{
    public function resolved(Request $request, SupportChatService $chat)
    {
        $data = $request->validate(['action'=>['required','in:reopen,new']]);
        return response()->json(['conversation'=>$chat->publicView($chat->resolvedAction($request,$data['action']))])->header('Cache-Control','no-store');
    }
    public function show(Request $request, SupportChatService $chat)
    {
        return response()->json(['conversation' => $chat->publicView($chat->current($request))])->header('Cache-Control', 'no-store');
    }

    public function send(Request $request, SupportChatService $chat)
    {
        $data = $request->validate(['contact'=>['sometimes','array:name,email,phone'],'contact.name'=>['required_with:contact','string','max:100'],'contact.email'=>['required_with:contact','email','max:255'],'contact.phone'=>['nullable','string','max:30'],'request_human'=>['sometimes','boolean'], 'language' => ['sometimes', 'in:en,ms,zh'], 'attachment_id'=>['nullable','uuid'], 'content' => ['nullable', 'string', 'max:2000'], 'client_message_id' => ['required', 'uuid']]);
        $data['content'] = trim($data['content'] ?? '');
        abort_if($data['content'] === '' && empty($data['attachment_id']), 422, 'Message cannot be empty.');
        $conversation = $chat->sendCustomer($request, $data);
        app(FaqAutoReplyService::class)->reply($conversation, $data);

        return response()->json(['conversation' => $chat->publicView($conversation->fresh())])->header('Cache-Control', 'no-store');
    }
}

