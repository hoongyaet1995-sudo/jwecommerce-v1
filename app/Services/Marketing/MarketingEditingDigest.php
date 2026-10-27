<?php
namespace App\Services\Marketing;
/** Stable across MySQL JSON key normalization; scene/interval list order remains significant. */
class MarketingEditingDigest {
 public static function hash(array $data):string{return hash('sha256',json_encode(self::ordered($data),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));}
 private static function ordered(array $data):array{if(!array_is_list($data))ksort($data);foreach($data as &$v)if(is_array($v))$v=self::ordered($v);unset($v);return $data;}
}
