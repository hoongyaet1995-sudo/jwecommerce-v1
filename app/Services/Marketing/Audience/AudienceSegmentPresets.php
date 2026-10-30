<?php
namespace App\Services\Marketing\Audience;
class AudienceSegmentPresets {
 public function all():array {
  $r=fn($f,$o,$v,$extra=[])=>array_merge(['field'=>$f,'operator'=>$o,'value'=>$v],$extra);
  $rows=[
   ['blank','pencil-square','Blank Segment','Start with no rules and build your own audience.','',[]],
   ['all_subscribers','envelope-check','All Subscribers','Customers currently subscribed to email marketing.','All Email Subscribers',[$r('subscription','eq','subscribed',['channel'=>'email'])]],
   ['all_whatsapp_subscribers','whatsapp','WhatsApp Subscribers','Contacts with recorded permission to receive WhatsApp messages.','All WhatsApp Subscribers',[$r('subscription','eq','subscribed',['channel'=>'whatsapp'])]],
   ['new_customers','person-plus','New Customers','Customers who made their first successful purchase in the last 30 days.','New Customers - Last 30 Days',[$r('first_paid_at','within_last_days',30)]],
   ['active_customers','lightning-charge','Active Customers','Customers who made a successful purchase in the last 90 days.','Active Customers - Last 90 Days',[$r('last_paid_at','within_last_days',90)]],
   ['at_risk','hourglass-split','At Risk','Last successful purchase was between 90 and 180 days ago.','At Risk Customers',[$r('last_paid_at','between_days_ago',90,['value_to'=>180])]],
   ['lapsed_customers','moon-stars','Lapsed Customers','Last successful purchase was more than 180 days ago.','Lapsed Customers - 180 Days',[$r('last_paid_at','more_than_days_ago',180)]],
   ['vip_customers','gem','VIP Customers','Customers above a net paid spending amount you choose.','VIP Customers',[$r('net_minor','gte',100000,['currency'=>'MYR'])]],
   ['repeat_buyers','arrow-repeat','Repeat Buyers','Customers with two or more successful orders.','Repeat Buyers',[$r('paid_orders','gte',2)]],
   ['never_purchased','cart-x','Never Purchased','Contacts who have not completed a successful order.','Never Purchased',[$r('paid_orders','eq',0)]],
   ['birthday_month','cake2','Birthday Month','Customers with a birthday in the selected month.','Birthday - '.now()->format('F'),[$r('birthday_month','eq',(int)now()->format('n'))]],
   ['category_buyers','tags','Category Buyers','Customers who purchased from a selected product category.','Category Buyers',[$r('category_purchased','eq','')]],
  ];$out=[];foreach($rows as [$key,$icon,$name,$description,$segmentName,$conditions])$out[$key]=['icon'=>$icon,'name'=>$name,'description'=>$description,'segment_name'=>$segmentName,'rules'=>['match'=>'all','conditions'=>$conditions],'available'=>true];return $out;
 }
}
