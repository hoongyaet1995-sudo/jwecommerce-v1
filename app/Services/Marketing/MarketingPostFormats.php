<?php
namespace App\Services\Marketing;
/** Implemented capabilities, not a claim about every format supported by each platform. */
final class MarketingPostFormats
{
 public static function all():array { return [
  'facebook'=>['Post'=>['image']],
  'instagram'=>['Post'=>['image']],
  'tiktok'=>['Post'=>['image'],'Reel'=>['video']],
  'youtube'=>['Reel'=>['video']],
 ]; }
 public static function supports(string $platform,string $type,?string $kind):bool { return in_array($kind,self::all()[$platform][$type]??[],true); }
 public static function photoReady():bool { $base=rtrim(url('/marketing/post-delivery'),'/').'/';$prefix=(string)config('marketing.tiktok_photo_verified_prefix','');return str_starts_with($base,'https://')&&$prefix!==''&&str_ends_with($prefix,'/')&&str_starts_with($base,$prefix); }
}
