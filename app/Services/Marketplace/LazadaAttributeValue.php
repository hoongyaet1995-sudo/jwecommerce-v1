<?php
namespace App\Services\Marketplace;

use Illuminate\Validation\ValidationException;

final class LazadaAttributeValue
{
    public static function validate(array $attribute, mixed $value): void
    {
        $type=strtolower((string)($attribute['input_type']??''));
        $name=(string)($attribute['name']??'attribute');
        $reject=static function () use ($name): never {
            throw ValidationException::withMessages(['attributes'=>"Review the value and supported input type for {$name}."]);
        };
        if ($type==='numeric') {
            if ((!is_string($value)&&!is_int($value)&&!is_float($value)) || !is_numeric($value) || !is_finite((float)$value)) $reject();
            return;
        }
        if ($type==='img') {
            $images=$name==='__images__'?$value:[$value];
            if (!is_array($images)||!array_is_list($images)||!$images) $reject();
            foreach ($images as $image) if (!is_string($image)||!preg_match('#\Ahttps://[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)*\.slatic\.net/[^\s]+\z#D',$image)) $reject();
            return;
        }
        if (!is_string($value)) $reject();
        if (in_array($type,['text','richtext'],true)) return;
        if (!in_array($type,['singleselect','multiselect','enuminput','multienuminput'],true)) $reject();
        $selected=in_array($type,['multiselect','multienuminput'],true)?array_map('trim',explode(',',$value)):[$value];
        if (in_array('',$selected,true)||count(array_unique($selected))!==count($selected)) $reject();
        if (in_array($type,['enuminput','multienuminput'],true)) return;
        $options=$attribute['options']??null;
        if (!is_array($options)||!array_is_list($options)) $reject();
        $allowed=[];
        foreach ($options as $option) {
            if (!is_array($option)||!is_string($option['en_name']??null)) $reject();
            $allowed[]=$option['en_name'];
        }
        foreach ($selected as $option) if (!in_array($option,$allowed,true)) $reject();
    }
}
