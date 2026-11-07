<?php
// Curated cultural occasion dates, not substitute public-holiday/closure dates.
// Verified 2026-11-07 against the primary sources below. Never extrapolate lunar dates.
$my='https://www.malaysia.gov.my/en/calendar';
$sg='https://www.mom.gov.sg/newsroom/press-releases/2025/0616-public-holidays-for-2026';
$uk='https://www.gov.uk/bank-holidays';
$entries=[];
foreach(['Malaysia'=>$my,'Singapore'=>$sg,'United Kingdom'=>$uk] as $market=>$source){
 foreach(['2026-12-25'=>'Christmas Day','2027-01-01'=>'New Year’s Day'] as $date=>$name)$entries[]=['id'=>$market.'-'.$date,'date'=>$date,'name'=>$name,'markets'=>[$market],'source'=>$source,'verified_at'=>'2026-11-07'];
}
foreach(['2026-08-31'=>'National Day','2026-09-16'=>'Malaysia Day','2026-11-08'=>'Deepavali'] as $date=>$name)$entries[]=['id'=>'MY-'.$date,'date'=>$date,'name'=>$name,'markets'=>['Malaysia'],'source'=>$my,'verified_at'=>'2026-11-07'];
foreach(['2026-01-01'=>'New Year’s Day','2026-02-17'=>'Chinese New Year','2026-02-18'=>'Chinese New Year','2026-04-03'=>'Good Friday','2026-05-01'=>'Labour Day','2026-05-31'=>'Vesak Day','2026-08-09'=>'National Day','2026-11-08'=>'Deepavali'] as $date=>$name)$entries[]=['id'=>'SG-'.$date,'date'=>$date,'name'=>$name,'markets'=>['Singapore'],'source'=>$sg,'verified_at'=>'2026-11-07'];
return ['entries'=>$entries];
