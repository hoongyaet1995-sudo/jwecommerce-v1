<?php
namespace App\Services\Marketplace;

/** Per-channel fee review only; package constraints and cross-channel rules remain separate. */
final class ShopeeLogisticsSelection
{
    public static function validate(array $channel,array $selection): void
    {
        if (($channel['enabled'] ?? null)!==true || !is_int($selection['logistic_id'] ?? null)
            || $selection['logistic_id']!==($channel['logistics_channel_id'] ?? null)) {
            throw new \DomainException('Select an enabled logistics channel from this store.');
        }
        $type=$channel['fee_type'] ?? null;
        if (!in_array($type,['SIZE_SELECTION','SIZE_INPUT','FIXED_DEFAULT_PRICE','CUSTOM_PRICE'],true)) {
            throw new \RuntimeException('Logistics fee rules are unavailable.');
        }
        if (array_key_exists('enabled',$selection) && $selection['enabled']!==true) {
            throw new \DomainException('Only selected, enabled logistics channels may be submitted.');
        }
        if (array_key_exists('is_free',$selection) && !is_bool($selection['is_free'])) throw new \DomainException('Invalid free-shipping selection.');
        if (($selection['is_free'] ?? false) && ($channel['block_seller_cover_shipping_fee'] ?? null)!==false) {
            throw new \DomainException('Seller-covered shipping is not confirmed available for this channel.');
        }
        if ($type==='SIZE_SELECTION') {
            $size=$selection['size_id'] ?? null;
            if ((!is_string($size) && !is_int($size)) || !is_array($channel['size_list'] ?? null)) {
                throw new \DomainException('Choose a platform shipping size.');
            }
            $matches=array_filter($channel['size_list'],fn($row)=>isset($row['size_id']) && (string)$row['size_id']===(string)$size);
            if (count($matches)!==1) throw new \DomainException('The selected shipping size is not available.');
        } elseif (array_key_exists('size_id',$selection)) {
            throw new \DomainException('This channel does not accept a size selection.');
        }
        if ($type==='CUSTOM_PRICE') {
            $fee=$selection['shipping_fee'] ?? null;
            if ((!is_int($fee) && !is_float($fee)) || !is_finite((float)$fee) || $fee<0) {
                throw new \DomainException('Enter a valid non-negative shipping fee.');
            }
        } elseif (array_key_exists('shipping_fee',$selection)) {
            throw new \DomainException('This channel uses platform-calculated shipping fees.');
        }
    }
}
