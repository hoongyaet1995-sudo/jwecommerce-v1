<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Notifications\SupportHumanRequested;
use Illuminate\Http\Request;

class SupportAlertController extends Controller
{
    public function index(Request $request)
    {
        $query=$request->user()->notifications()->where('type',SupportHumanRequested::class);
        return response()->json(['unreadCount'=>(clone $query)->whereNull('read_at')->count(),
            'notifications'=>$query->latest()->limit(50)->get()->map(fn($n)=>[
                'id'=>$n->id,'title'=>$n->data['title'],'description'=>$n->data['description'],
                'conversationId'=>$n->data['conversation_id'],'time'=>$n->created_at->diffForHumans(),
                'unread'=>$n->read_at===null,
            ])])->header('Cache-Control','no-store');
    }
    public function read(Request $request, string $id)
    {
        $query=$request->user()->notifications()->where('type',SupportHumanRequested::class)->whereNull('read_at');
        if ($id!=='all') {
            abort_unless($request->user()->notifications()->where('type',SupportHumanRequested::class)->whereKey($id)->exists(),404);
            $query->whereKey($id);
        }
        $query->update(['read_at'=>now()]);
        return $this->index($request);
    }
}
