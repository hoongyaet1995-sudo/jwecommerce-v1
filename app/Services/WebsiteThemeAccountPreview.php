<?php
namespace App\Services;
/** Explicit non-sensitive empty fixture. Never chooses a real customer. */
final class WebsiteThemeAccountPreview
{
 public static function empty(): array
 {
  return ['user'=>['id'=>0,'name'=>'Sample customer','email'=>'sample@example.com','firstName'=>'Sample','lastName'=>'Customer','phone'=>'','avatar'=>null,'memberSince'=>null], 'orders'=>[],'addresses'=>[],'wishlist'=>[], 'dashboard'=>['orderCount'=>0,'activeLogisticsCount'=>0], 'vouchers'=>['eligible'=>[],'used'=>[],'expired'=>[],'counts'=>['eligible'=>0,'used'=>0,'expired'=>0]], 'returnPolicy'=>app(\App\Support\ReturnPolicySettings::class)->get(), 'gaps'=>['password','two_factor','sessions','membership_points']];
 }
}
