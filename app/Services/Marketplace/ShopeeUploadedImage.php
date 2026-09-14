<?php

namespace App\Services\Marketplace;

/** Validates the response for one uploaded image, without exposing provider error bodies. */
final class ShopeeUploadedImage
{
    public static function identity(array $payload): string
    {
        if (($payload['error'] ?? null)!=='') throw new \RuntimeException('Shopee image upload was not confirmed.');
        $response=$payload['response'] ?? [];
        if (!is_array($response)) throw new \RuntimeException('Shopee image response was incomplete.');
        $id=$response['image_info']['image_id'] ?? null;
        if (isset($response['image_info_list'])) {
            $rows=$response['image_info_list'];
            if (!is_array($rows) || !array_is_list($rows) || count($rows)!==1
                || !is_array($rows[0]) || ($rows[0]['id'] ?? null)!==0
                || !array_key_exists('error',$rows[0]) || !in_array($rows[0]['error'],['',null],true)) {
                throw new \RuntimeException('Shopee image upload contained a failed or unexpected image.');
            }
            $rowId=$rows[0]['image_info']['image_id'] ?? null;
            if ($id!==null && $id!==$rowId) throw new \RuntimeException('Shopee image identities did not agree.');
            $id=$rowId;
        }
        if (!is_string($id) || trim($id)==='' || $id==='-') throw new \RuntimeException('Shopee image identity was missing.');
        return $id;
    }
}
