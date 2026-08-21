<?php

namespace App\Domain\Cms;

use JsonException;
use stdClass;

final class PageDocumentHasher
{
    /**
     * @throws JsonException
     */
    public static function hash(array $document): string
    {
        return hash('sha256', json_encode(
            self::canonicalize($document),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }

    private static function canonicalize(mixed $value): mixed
    {
        if (is_float($value)
            && is_finite($value)
            && floor($value) === $value
            && $value >= PHP_INT_MIN
            && $value <= PHP_INT_MAX) {
            return (int) $value;
        }

        if ($value instanceof stdClass) {
            $properties = get_object_vars($value);
            ksort($properties, SORT_STRING);

            foreach ($properties as $key => $nestedValue) {
                $properties[$key] = self::canonicalize($nestedValue);
            }

            return (object) $properties;
        }

        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(self::canonicalize(...), $value);
        }

        ksort($value, SORT_STRING);

        foreach ($value as $key => $nestedValue) {
            $value[$key] = self::canonicalize($nestedValue);
        }

        return $value;
    }
}
