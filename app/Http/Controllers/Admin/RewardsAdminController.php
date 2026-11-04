<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\StoreSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
class RewardsAdminController extends Controller {
public function settings(){return response()->json(['data'=>StoreSetting::where('key','rewards_rules')->value('value')??['coins_per_unit'=>100,'max_discount_percent'=>20,'minimum_coins'=>100,'expiry_months'=>12,'enabled'=>false]]);}
public function saveSettings(Request $r){$d=$r->validate(['coins_per_unit'=>'required|integer|min:1|max:1000000','max_discount_percent'=>'required|integer|min:1|max:100','minimum_coins'=>'required|integer|min:0|max:100000000','expiry_months'=>'nullable|integer|min:1|max:120','enabled'=>'required|boolean']);StoreSetting::updateOrCreate(['key'=>'rewards_rules'],['value'=>$d]);return response()->json(['data'=>$d]);}
public function index(){return response()->json(['data'=>StoreSetting::where('key','like','rewards_offer:%')->orderByDesc('id')->get()->map(fn($s)=>$s->value)->values()]);}
public function store(Request $r){return $this->save($r,(string)Str::uuid(),true);}
public function update(Request $r,string $id){abort_unless(StoreSetting::where('key','rewards_offer:'.$id)->exists(),404);return $this->save($r,$id,false);}
private function save(Request $r,string $id,bool $new){$d=$r->validate(['name'=>'required|string|max:255','description'=>'nullable|string|max:5000','scope'=>'required|in:all,selected','product_ids'=>'present|array|max:10000','product_ids.*'=>'integer|distinct|exists:products,id','rate'=>'required|numeric|min:0.01|max:100','minimum_spend'=>'required|integer|min:0|max:100000000','maximum_reward'=>'required|integer|min:1|max:100000000','starts_at'=>'nullable|date','ends_at'=>'nullable|date|after_or_equal:starts_at','enabled'=>'required|boolean']);$d['scope']='all';$d['product_ids']=[];if($d['scope']==='selected'&&!count($d['product_ids']))throw \Illuminate\Validation\ValidationException::withMessages(['product_ids'=>'Choose at least one product.']);$d['id']=$id;StoreSetting::updateOrCreate(['key'=>'rewards_offer:'.$id],['value'=>$d]);return response()->json(['data'=>$d],$new?201:200);}
}
