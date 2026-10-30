<?php
namespace App\Services\Marketing;
use App\Models\ManagedSocialAccount;
use App\Services\Marketing\Audience\AudienceDirectory;
use Illuminate\Support\Facades\DB;
final class MarketingWhatsAppOptOut {
 /** Called only after the existing verified inbound account routing and replay checks. */
 public function receive(ManagedSocialAccount $account,array $event):bool {
  $text=trim((string)($event['text']['body']??$event['button']['text']??$event['interactive']['button_reply']['title']??''));
  if(!in_array(mb_strtolower($text),['stop','unsubscribe','opt out','退订','取消订阅'],true))return false;
  if($account->platform!=='whatsapp'||$account->merchant_key!==app(AudienceDirectory::class)->merchant()||!$account->inbound_enabled||!preg_match('/^[1-9][0-9]{7,14}$/D',$event['from']??''))return false;
  app(AudienceDirectory::class)->subscribe('whatsapp','+'.$event['from'],'unsubscribed','whatsapp_reply',now());return true;
 }
}
