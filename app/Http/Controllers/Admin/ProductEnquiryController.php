<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\{ProductEnquiry,User};
use App\Services\ProductEnquiryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class ProductEnquiryController extends Controller
{
    private function project(ProductEnquiry $row,bool $detail=false): array {
        $data=['id'=>$row->id,'reference'=>$row->reference,'name'=>$row->name,'email'=>$row->email,'phone'=>$row->phone,'productTitle'=>$row->product_title,'product'=>$row->product_snapshot,'status'=>$row->status,'outcome'=>$row->outcome,'assignedTo'=>$row->assigned_to,'assignee'=>$row->assignee?->name,'createdAt'=>$row->created_at->toIso8601String(),'version'=>$row->version,'isCustomer'=>$row->customer_user_id!==null];
        if($detail){$data['support']=$row->supportConversation?['id'=>$row->supportConversation->id,'status'=>$row->supportConversation->status]:null;$data['requirements']=$row->requirements;$data['events']=$row->events->map(fn($e)=>['id'=>$e->id,'type'=>$e->type,'content'=>$e->content,'changes'=>$e->changes,'actor'=>$e->actor?->name,'at'=>$e->created_at->toIso8601String()]);}
        return $data;
    }
    public function index(Request $request){
        $data=$request->validate(['search'=>['nullable','string','max:120'],'status'=>['nullable',Rule::in(['new','following_up','closed'])],'page'=>['nullable','integer','min:1']]);
        $query=ProductEnquiry::with('assignee')->latest('created_at')->orderByDesc('id');
        if(filled($data['status']??null))$query->where('status',$data['status']);
        if(filled($data['search']??null)){$term='%'.addcslashes($data['search'],'%_\\').'%';$query->where(fn($q)=>$q->where('reference','like',$term)->orWhere('name','like',$term)->orWhere('email','like',$term)->orWhere('product_title','like',$term));}
        $rows=$query->paginate(20);$counts=ProductEnquiry::selectRaw('status,count(*) as aggregate')->groupBy('status')->pluck('aggregate','status');
        return response()->json(['data'=>$rows->map(fn($row)=>$this->project($row)),'pagination'=>['currentPage'=>$rows->currentPage(),'lastPage'=>$rows->lastPage(),'total'=>$rows->total()],'counts'=>['all'=>$counts->sum(),'new'=>(int)($counts['new']??0),'following_up'=>(int)($counts['following_up']??0),'closed'=>(int)($counts['closed']??0)],'staff'=>User::where('is_admin',true)->orderBy('name')->get(['id','name'])])->header('Cache-Control','private, no-store');
    }
    public function show(ProductEnquiry $enquiry){return response()->json(['data'=>$this->project($enquiry->load(['assignee','events.actor','supportConversation']),true)])->header('Cache-Control','private, no-store');}
    public function update(Request $request,ProductEnquiry $enquiry,ProductEnquiryService $service){
        $data=$request->validate(['version'=>['required','integer','min:1'],'status'=>['required',Rule::in(['new','following_up','closed'])],'assigned_to'=>['nullable','integer',Rule::exists('users','id')->where('is_admin',true)],'outcome'=>['required_if:status,closed','nullable',Rule::in(['won','lost'])]]);
        return $this->show($service->change($enquiry,$data,$request->user()->id));
    }
    public function note(Request $request,ProductEnquiry $enquiry,ProductEnquiryService $service){
        $data=$request->validate(['content'=>['required','string','max:2000']]);
        return $this->show($service->addNote($enquiry,$data['content'],$request->user()->id))->setStatusCode(201);
    }
}
