<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Notifications\SupportHumanRequested;
use Illuminate\Http\Request;

class SupportAlertController extends Controller
{
    public function index(Request $request)
    {
        $query=$request->user()->notifications()->whereIn('type',[SupportHumanRequested::class,\App\Notifications\SupportMessageReceived::class]);
        $notifications=(clone $query)->latest()->limit(50)->get();
        $channels=\App\Models\SupportConversation::whereIn('id',$notifications->map(fn($n)=>$n->data['conversation_id']))->pluck('channel','id');
        return response()->json(['unreadCount'=>(clone $query)->whereNull('read_at')->count(),
            'notifications'=>$notifications->map(fn($n)=>[
                'id'=>$n->id,'title'=>$n->data['title'],'description'=>$n->data['description'],
                'conversationId'=>$n->data['conversation_id'],'channel'=>$channels[$n->data['conversation_id']]??null,'time'=>$n->created_at->diffForHumans(),
                'unread'=>$n->read_at===null,
            ])])->header('Cache-Control','no-store');
    }
    public function read(Request $request, string $id)
    {
        $query=$request->user()->notifications()->whereIn('type',[SupportHumanRequested::class,\App\Notifications\SupportMessageReceived::class])->whereNull('read_at');
        if ($id!=='all') {
            abort_unless($request->user()->notifications()->whereIn('type',[SupportHumanRequested::class,\App\Notifications\SupportMessageReceived::class])->whereKey($id)->exists(),404);
            $query->whereKey($id);
        }
        $query->update(['read_at'=>now()]);
        return $this->index($request);
    }
}
