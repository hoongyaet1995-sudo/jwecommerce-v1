<?php
namespace App\Mail;
use Illuminate\Mail\Mailable;
class MarketingCampaignTestMail extends Mailable {
 public function __construct(public array $snapshot){}
 public function build(){return $this->from($this->snapshot['from_address'],$this->snapshot['from_name'])->subject($this->snapshot['subject'])->html($this->snapshot['html']);}
}
