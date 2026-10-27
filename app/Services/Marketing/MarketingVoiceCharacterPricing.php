<?php
namespace App\Services\Marketing;

/** Trusted character rate only; unexpected provider units must stop further spending. */
class MarketingVoiceCharacterPricing
{
    public function bound(string $text, array $rate): int
    {
        abort_unless(($rate['model']??null)==='eleven_multilingual_v2' && ($rate['billing_unit']??null)==='character'
            && is_int($rate['per_character_microusd']??null) && $rate['per_character_microusd']>0
            && is_int($rate['cap_microusd']??null) && $rate['cap_microusd']>0
            && !empty($rate['pricing_verified_at']) && !empty($rate['pricing_valid_until'])
            && now()->lt($rate['pricing_valid_until']),423,'声音单价未核验或已过期，未发送请求。');
        $count=mb_strlen($text,'UTF-8');
        abort_unless(mb_check_encoding($text,'UTF-8') && trim($text)!=='' && $count<=4000,422,'口播文案无效。');
        $cost=$count*$rate['per_character_microusd'];
        abort_unless($cost<=$rate['cap_microusd'],423,'口播超过单次费用上限，未发送请求。');
        return $cost;
    }
    public function actual(mixed $units,string $text,array $rate): ?int
    {
        $bound=$this->bound($text,$rate);
        if(!is_scalar($units)||!preg_match('/^\d+$/D',(string)$units))return null;
        // Credits must not be priced as characters without a verified conversion.
        if((float)$units!==(float)mb_strlen($text,'UTF-8'))return null;
        $cost=(int)$units*$rate['per_character_microusd'];
        return $cost<=$bound?$cost:null;
    }
}
