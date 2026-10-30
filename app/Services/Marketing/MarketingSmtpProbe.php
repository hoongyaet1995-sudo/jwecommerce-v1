<?php
namespace App\Services\Marketing;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
class MarketingSmtpProbe {
 public function check(array $c):void {
  // Do not allow a settings request to reach private infrastructure, even through DNS.
  $addresses=array_merge(gethostbynamel($c['host'])?:[],array_column(dns_get_record($c['host'],DNS_AAAA)?:[],'ipv6'));
  abort_unless(count($addresses)>0,422,'The email server address could not be resolved.');foreach($addresses as $ip)abort_unless(filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE),422,'Choose an approved public email server.');
  $t=$this->transport($c);try{$t->start();}finally{$t->stop();}
 }
 public function transport(array $c):EsmtpTransport {
  $scheme=$c['scheme']??(($c['encryption']??null)==='ssl'?'smtps':'smtp');
  abort_unless(in_array($scheme,['smtp','smtps'],true)&&(($scheme==='smtps'&&(int)$c['port']===465)||($scheme==='smtp'&&(int)$c['port']===587)),422,'Use SSL on port 465 or STARTTLS on port 587.');
  $t=new EsmtpTransport($c['host'],(int)$c['port'],$scheme==='smtps');$t->setRequireTls(true);$t->setUsername($c['username']);$t->setPassword($c['password']);$t->getStream()->setTimeout(10);return $t;
 }
}
