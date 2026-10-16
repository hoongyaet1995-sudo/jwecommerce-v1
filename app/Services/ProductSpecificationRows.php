<?php

namespace App\Services;

final class ProductSpecificationRows
{
    /** Internal row identities are supplied by the system, never the merchant. */
    public function normalize(array $rows): array
    {
        foreach ($rows as $index => &$row) {
            if (is_array($row) && (!isset($row['id']) || $row['id'] === '')) {
                $row['id'] = 'spec-'.substr(hash('sha256', $index.'|'.json_encode($row)), 0, 24);
            }
        }
        unset($row);

        return $rows;
    }
}
