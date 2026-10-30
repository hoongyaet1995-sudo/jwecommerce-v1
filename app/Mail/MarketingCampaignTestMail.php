<?php
namespace App\Mail;
use Illuminate\Mail\Mailable;
class MarketingCampaignTestMail extends Mailable {
 public function __construct(public array $snapshot){}
 public function headers():\Illuminate\Mail\Mailables\Headers {return new \Illuminate\Mail\Mailables\Headers(text:isset($this->snapshot['unsubscribe_url'])?['List-Unsubscribe'=>'<'.$this->snapshot['unsubscribe_url'].'>','List-Unsubscribe-Post'=>'List-Unsubscribe=One-Click']:[]);}
 public function build(){return $this->from($this->snapshot['from_address'],$this->snapshot['from_name'])->subject($this->snapshot['subject'])->html($this->snapshot['html']);}
}
