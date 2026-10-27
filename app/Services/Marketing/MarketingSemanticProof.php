<?php
namespace App\Services\Marketing;
class MarketingSemanticProof {
 public static function seal(array $data):array {unset($data['seal']);$data['seal']=hash_hmac('sha256',MarketingEditingDigest::hash($data),(string)config('app.key'));return $data;}
 public static function valid(array $data):bool {$seal=$data['seal']??'';unset($data['seal']);return is_string($seal)&&hash_equals(hash_hmac('sha256',MarketingEditingDigest::hash($data),(string)config('app.key')),$seal);}
}
