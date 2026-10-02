<?php
namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
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
        $data = $request->validate(['content' => ['required', 'string', 'max:2000'], 'client_message_id' => ['required', 'uuid']]);
        $data['content'] = trim($data['content']);
        abort_if($data['content'] === '', 422, 'Message cannot be empty.');
        return response()->json(['conversation' => $chat->publicView($chat->sendCustomer($request, $data))])->header('Cache-Control', 'no-store');
    }
}
