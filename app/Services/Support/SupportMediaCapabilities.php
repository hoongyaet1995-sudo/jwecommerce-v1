<?php
namespace App\Services\Support;
class SupportMediaCapabilities {
 public function forChannel(string $channel):array {return ['images'=>in_array($channel,['web_guest','facebook','instagram','whatsapp'],true),'documents'=>in_array($channel,['web_guest','facebook','whatsapp'],true),'audio'=>$channel==='whatsapp','imageMaxBytes'=>5242880,'documentMaxBytes'=>10485760,'audioMaxBytes'=>16777216];}
}
