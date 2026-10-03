<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Support\FaqAutoReplyService;
use App\Services\Support\SupportChatService;
use Illuminate\Http\Request;

class SupportChatController extends Controller
{
    public function show(Request $request, SupportChatService $chat)
    {
        return response()->json(['conversation' => $chat->publicView($chat->current($request))])->header('Cache-Control', 'no-store');
    }

    public function send(Request $request, SupportChatService $chat)
    {
        $data = $request->validate(['language' => ['sometimes', 'in:en,ms,zh'], 'content' => ['required', 'string', 'max:2000'], 'client_message_id' => ['required', 'uuid']]);
        $data['content'] = trim($data['content']);
        abort_if($data['content'] === '', 422, 'Message cannot be empty.');
        $conversation = $chat->sendCustomer($request, $data);
        app(FaqAutoReplyService::class)->reply($conversation, $data);

        return response()->json(['conversation' => $chat->publicView($conversation)])->header('Cache-Control', 'no-store');
    }
}
