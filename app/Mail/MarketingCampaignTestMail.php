<?php
namespace App\Mail;
use Illuminate\Mail\Mailable;
class MarketingCampaignTestMail extends Mailable {
 public function __construct(public array $snapshot){}
 public function headers():\Illuminate\Mail\Mailables\Headers {return new \Illuminate\Mail\Mailables\Headers(messageId:$this->snapshot['message_id']??null,text:isset($this->snapshot['unsubscribe_url'])?['List-Unsubscribe'=>'<'.$this->snapshot['unsubscribe_url'].'>','List-Unsubscribe-Post'=>'List-Unsubscribe=One-Click']:[]);}
 public function build(){if(!empty($this->snapshot['bounce_address']))$this->withSymfonyMessage(fn($message)=>$message->returnPath($this->snapshot['bounce_address']));if(!empty($this->snapshot['reply_to']))$this->replyTo($this->snapshot['reply_to']);return $this->from($this->snapshot['from_address'],$this->snapshot['from_name'])->subject($this->snapshot['subject'])->html($this->snapshot['html']);}
}
