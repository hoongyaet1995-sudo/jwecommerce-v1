<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Services\Customers\ContactValueNormalizer as Normalize;
return new class extends Migration {
 public function up():void {
  DB::table('marketing_audience_contacts')->where('active',true)->select(['id','email','phone'])->orderBy('id')->chunkById(500,function($rows){
   $ids=[];foreach($rows as $row)if(!Normalize::email($row->email)&&!Normalize::phone($row->phone))$ids[]=$row->id;
   if($ids)DB::table('marketing_audience_contacts')->whereIn('id',$ids)->update(['active'=>false,'updated_at'=>now()]);
  });
 }
 // Do not reactivate unreachable contacts on rollback or delete historical data.
 public function down():void {}
};
