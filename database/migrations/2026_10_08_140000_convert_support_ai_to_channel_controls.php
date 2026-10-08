<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up():void {
  DB::transaction(function(){
   $row=DB::table('store_settings')->where('key','support_faq')->lockForUpdate()->first();
   if(!$row)return;
   $value=json_decode($row->value,true);
   $defaults=\App\Services\Support\SupportAiPolicy::DEFAULTS;
   $channels=array_replace($defaults,$value['aiChannels']??[]);
   if(!($value['autoReplyEnabled']??false))$channels=array_fill_keys(array_keys($defaults),false);
   $value['aiChannels']=$channels;
   $value['autoReplyEnabled']=in_array(true,$channels,true);
   $value['version']=($value['version']??0)+1;
   DB::table('store_settings')->where('id',$row->id)->update(['value'=>json_encode($value),'updated_at'=>now()]);
  });
 }
 public function down():void { /* Per-channel choices must not be overwritten on rollback. */ }
};
