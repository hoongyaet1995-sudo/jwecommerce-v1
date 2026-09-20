<?php

namespace App\Services\Marketplace;

use Illuminate\Validation\ValidationException;

/** Validates browser-reviewed Lazada values against the current provider schema. */
final class LazadaReviewedAttributes
{
    public static function bind(array $schema, array $source, array $reviewed): array
    {
        if (array_diff(array_keys($reviewed), ['normal', 'skus', 'imageBindings'])) {
            self::reject('Unsupported reviewed attribute fields.');
        }
        $normal = $reviewed['normal'] ?? [];
        $skus = $reviewed['skus'] ?? [];
        $bindings = $reviewed['imageBindings'] ?? [];
        if (!is_array($normal) || !is_array($skus) || !is_array($bindings)
            || array_is_list($normal) || array_is_list($skus) || array_is_list($bindings)) {
            self::reject('Reviewed attributes are malformed.');
        }

        $sourceSkus = [];
        foreach ($source['variants'] ?? [] as $variant) {
            $sku = $variant['sku'] ?? null;
            if (!is_string($sku) || trim($sku) === '' || isset($sourceSkus[$sku])) {
                self::reject('JW variant identities are invalid.');
            }
            $sourceSkus[$sku] = true;
        }
        $ownedImages = [];
        foreach ($source['images'] ?? [] as $image) {
            $id = $image['id'] ?? null;
            if (!is_int($id) || $id < 1 || isset($ownedImages[$id])) self::reject('JW product images are invalid.');
            $ownedImages[$id] = true;
        }

        $definitions = ['normal' => [], 'sku' => []];
        foreach ($schema as $attribute) {
            if (!is_array($attribute)) self::reject('Lazada attribute schema is invalid.');
            $name = $attribute['name'] ?? null;
            $scope = $attribute['attribute_type'] ?? null;
            if (!is_string($name) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $name)
                || in_array($name, ['__proto__', 'prototype', 'constructor'], true)
                || !in_array($scope, ['normal', 'sku'], true)
                || isset($definitions[$scope][$name])) {
                self::reject('Lazada attribute schema is invalid.');
            }
            if ($name === '__images__') continue;
            $definitions[$scope][$name] = $attribute;
        }

        foreach ($normal as $name => $_) {
            if (!is_string($name) || !isset($definitions['normal'][$name])) self::reject('Unknown Lazada product attribute.');
        }
        foreach ($skus as $sku => $values) {
            if (!is_string($sku) || !isset($sourceSkus[$sku]) || !is_array($values) || array_is_list($values)) self::reject('Unknown Lazada variant.');
            foreach ($values as $name => $_) {
                if (!is_string($name) || !isset($definitions['sku'][$name])) self::reject('Unknown Lazada variant attribute.');
            }
        }

        $expectedImageKeys = [];
        $result = ['normal' => [], 'skus' => [], 'jw_attribute_image_ids' => []];
        foreach ($definitions['normal'] as $name => $attribute) {
            $type = strtolower((string) ($attribute['input_type'] ?? ''));
            $required = (bool) ($attribute['is_mandatory'] ?? false);
            if ($type === 'img') {
                $key = "lazada:product:{$name}";
                $expectedImageKeys[$key] = true;
                if ($required && !array_key_exists($key, $bindings)) self::reject("Complete required normal field {$name}.");
                continue;
            }
            if (!array_key_exists($name, $normal)) {
                if ($required) self::reject("Complete required normal field {$name}.");
                continue;
            }
            LazadaAttributeValue::validate($attribute, $normal[$name]);
            $result['normal'][$name] = $normal[$name];
        }
        foreach ($sourceSkus as $sku => $_) {
            foreach ($definitions['sku'] as $name => $attribute) {
                $type = strtolower((string) ($attribute['input_type'] ?? ''));
                $required = (bool) ($attribute['is_mandatory'] ?? false)
                    || (count($sourceSkus) > 1 && ($attribute['is_sale_prop'] ?? 0) == 1);
                if ($type === 'img') {
                    $key = "lazada:variant:{$sku}:{$name}";
                    $expectedImageKeys[$key] = true;
                    if ($required && !array_key_exists($key, $bindings)) self::reject("Complete required SKU field {$name} for {$sku}.");
                    continue;
                }
                if (!array_key_exists($name, $skus[$sku] ?? [])) {
                    if ($required) self::reject("Complete required SKU field {$name} for {$sku}.");
                    continue;
                }
                LazadaAttributeValue::validate($attribute, $skus[$sku][$name]);
                $result['skus'][$sku][$name] = $skus[$sku][$name];
            }
        }
        foreach ($bindings as $key => $imageId) {
            if (!is_string($key) || !isset($expectedImageKeys[$key]) || !is_int($imageId) || !isset($ownedImages[$imageId])) {
                self::reject('Select a current JW product image for each Lazada image attribute.');
            }
            $result['jw_attribute_image_ids'][$key] = $imageId;
        }
        return $result;
    }

    private static function reject(string $message): never
    {
        throw ValidationException::withMessages(['attributes' => $message]);
    }
}
