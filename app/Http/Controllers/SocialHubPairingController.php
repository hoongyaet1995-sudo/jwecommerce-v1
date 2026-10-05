<?php
namespace App\Http\Controllers;
use App\Services\Social\SocialProtocol;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
final class SocialHubPairingController extends Controller {
 public function __invoke(Request $r){
  $code=(string)$r->header('X-Social-Pairing-Code');abort_unless($r->getQueryString()===null&&preg_match('/^[a-f0-9]{64}$/D',$code),403);
  $d=$r->validate(['client_id'=>'required|uuid','key_id'=>'required|uuid','secret'=>'required|string|size:64','registered_domain'=>'required|string|max:253','hub_url'=>'required|in:https://social.jwaistudio.online']);
  abort_unless($d['registered_domain']===strtolower((string)parse_url(config('app.url'),PHP_URL_HOST)),403);
  abort_unless(SocialProtocol::verify(SocialProtocol::requestHeaders($r),$d['secret'],'hub-to-client','POST','/api/social/hub/pair',$r->getContent()),403);
  DB::transaction(function()use($d,$code){
   $token=DB::table('social_pairing_tokens')->where('token_hash',hash('sha256',$code))->lockForUpdate()->first();
   abort_unless($token&& !$token->consumed_at && \Illuminate\Support\Carbon::parse($token->expires_at)->isFuture()&&$token->domain===$d['registered_domain'],403);
   // The unique settings ID also prevents two simultaneous codes from replacing an installation.
   abort_if(DB::table('social_hub_settings')->where('id',1)->exists(),409,'Website already paired.');
   DB::table('social_hub_settings')->insert(['id'=>1,'hub_url'=>$d['hub_url'],'client_id'=>$d['client_id'],'key_id'=>$d['key_id'],'registered_domain'=>$d['registered_domain'],'encrypted_secret'=>Crypt::encryptString($d['secret']),'enabled'=>true,'created_at'=>now(),'updated_at'=>now()]);
   DB::table('social_pairing_tokens')->where('token_hash',$token->token_hash)->update(['consumed_at'=>now()]);
  });
  $heartbeat=app(\App\Services\Social\SocialHubInstallation::class)->heartbeat();
  return SocialProtocol::response(['status'=>'paired','client_id'=>$d['client_id'],'key_id'=>$d['key_id'],'heartbeat'=>$heartbeat],SocialProtocol::requestHeaders($r),$d['secret'],'hub-to-client');
 }
}
