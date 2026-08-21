<?php

namespace App\Domain\Cms;

use Illuminate\Validation\ValidationException;
use JsonException;
use RuntimeException;
use stdClass;

/**
 * Preserves JSON object/list token semantics at the HTTP and Eloquent-cast
 * boundaries without changing PageDocumentContract's array-based API.
 */
final class PageDocumentJsonCodec
{
    /** @var array<string, mixed>|null */
    private static ?array $cachedManifest = null;

    /** @return array<string, mixed> */
    public function decodeDocument(string $json): array
    {
        return $this->normalizeDocumentToken($this->decodeRootObject($json));
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function decodeDraftEnvelope(string $json): array
    {
        $envelope = $this->decodeRootObject($json);
        $properties = get_object_vars($envelope);
        $errors = [];

        foreach ($properties as $field => $_value) {
            if (! in_array($field, ['lockVersion', 'document'], true)) {
                $errors[$field][] = 'This field is not supported by the page draft request.';
            }
        }

        if (array_key_exists('document', $properties) && ! $properties['document'] instanceof stdClass) {
            $errors['document'][] = 'This value must be a JSON object.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $properties;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function normalizeDocumentToken(stdClass $document): array
    {
        $errors = [];
        $normalized = $this->normalizeValue($document, $this->manifest()['document'], '', $errors);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $normalized instanceof stdClass ? [] : $normalized;
    }

    /**
     * Restore object-declared empty values after Eloquent's array cast has
     * decoded both `{}` and `[]` to the same PHP empty array.
     *
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    public function canonicalizeDocument(array $document): array
    {
        $restored = $this->restoreValue($document, $this->manifest()['document']);

        return $restored instanceof stdClass ? [] : $restored;
    }

    /**
     * @param  array<string, mixed>  $document
     *
     * @throws JsonException
     */
    public function encodeDocument(array $document): string
    {
        return json_encode(
            $this->canonicalizeDocument($document),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    /** @throws ValidationException */
    private function decodeRootObject(string $json): stdClass
    {
        try {
            $value = json_decode($json, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages([
                'request' => ['The request body must contain valid JSON.'],
            ]);
        }

        if (! $value instanceof stdClass) {
            throw ValidationException::withMessages([
                'request' => ['The request body must contain a JSON object.'],
            ]);
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, list<string>>  $errors
     */
    private function normalizeValue(mixed $value, array $schema, string $path, array &$errors): mixed
    {
        if (($schema['$ref'] ?? null) === 'section') {
            return $this->normalizeSection($value, $path, $errors);
        }

        $schema = $this->resolveSchema($schema);

        if (($schema['type'] ?? null) === 'object') {
            if (! $value instanceof stdClass) {
                $errors[$path][] = 'This value must be a JSON object.';

                return $this->normalizeGeneric($value);
            }

            return $this->normalizeObject($value, $schema, $path, $errors);
        }

        if (($schema['type'] ?? null) === 'array') {
            if (! is_array($value)) {
                $errors[$path][] = 'This value must be a JSON array.';

                return $this->normalizeGeneric($value);
            }

            $normalized = [];

            foreach ($value as $index => $item) {
                $normalized[$index] = $this->normalizeValue(
                    $item,
                    $schema['items'],
                    $this->path($path, (string) $index),
                    $errors,
                );
            }

            return $normalized;
        }

        return $this->normalizeGeneric($value);
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, list<string>>  $errors
     */
    private function normalizeObject(stdClass $value, array $schema, string $path, array &$errors): array|stdClass
    {
        $properties = get_object_vars($value);

        if ($properties === []) {
            return new stdClass;
        }

        foreach ($properties as $field => $fieldValue) {
            $properties[$field] = isset($schema['fields'][$field])
                ? $this->normalizeValue(
                    $fieldValue,
                    $schema['fields'][$field],
                    $this->path($path, $field),
                    $errors,
                )
                : $this->normalizeGeneric($fieldValue);
        }

        return $properties;
    }

    /** @param array<string, list<string>> $errors */
    private function normalizeSection(mixed $value, string $path, array &$errors): array|stdClass
    {
        if (! $value instanceof stdClass) {
            $errors[$path][] = 'This value must be a JSON object.';

            return $this->normalizeGeneric($value);
        }

        $properties = get_object_vars($value);

        if ($properties === []) {
            return new stdClass;
        }

        $fields = $this->sectionFields($properties['type'] ?? null);

        foreach ($properties as $field => $fieldValue) {
            $properties[$field] = isset($fields[$field])
                ? $this->normalizeValue(
                    $fieldValue,
                    $fields[$field],
                    $this->path($path, $field),
                    $errors,
                )
                : $this->normalizeGeneric($fieldValue);
        }

        return $properties;
    }

    private function normalizeGeneric(mixed $value): mixed
    {
        if ($value instanceof stdClass) {
            $properties = get_object_vars($value);

            if ($properties === []) {
                return new stdClass;
            }

            return array_map($this->normalizeGeneric(...), $properties);
        }

        if (is_array($value)) {
            return array_map($this->normalizeGeneric(...), $value);
        }

        return $value;
    }

    /** @param array<string, mixed> $schema */
    private function restoreValue(mixed $value, array $schema): mixed
    {
        if (($schema['$ref'] ?? null) === 'section') {
            return $this->restoreSection($value);
        }

        $schema = $this->resolveSchema($schema);

        if (($schema['type'] ?? null) === 'object' && is_array($value)) {
            if ($value === []) {
                return new stdClass;
            }

            foreach ($value as $field => $fieldValue) {
                $value[$field] = isset($schema['fields'][$field])
                    ? $this->restoreValue($fieldValue, $schema['fields'][$field])
                    : $fieldValue;
            }

            return $value;
        }

        if (($schema['type'] ?? null) === 'array' && is_array($value)) {
            return array_map(
                fn (mixed $item): mixed => $this->restoreValue($item, $schema['items']),
                $value,
            );
        }

        return $value;
    }

    private function restoreSection(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if ($value === []) {
            return new stdClass;
        }

        $fields = $this->sectionFields($value['type'] ?? null);

        foreach ($value as $field => $fieldValue) {
            if (isset($fields[$field])) {
                $value[$field] = $this->restoreValue($fieldValue, $fields[$field]);
            }
        }

        return $value;
    }

    /** @return array<string, array<string, mixed>> */
    private function sectionFields(mixed $type): array
    {
        $manifest = $this->manifest();
        $fields = [];

        foreach ($manifest['section']['commonFields'] as $field) {
            $fields[$field] = array_replace($this->definition($field), ['required' => true]);
        }

        if (is_string($type) && isset($manifest['section']['sectionTypes'][$type])) {
            foreach ($manifest['section']['sectionTypes'][$type]['fields'] as $field) {
                $fields[$field] = $this->definition($field);
            }
        }

        return $fields;
    }

    /** @param array<string, mixed> $schema */
    private function resolveSchema(array $schema): array
    {
        if (! isset($schema['$ref'])) {
            return $schema;
        }

        $reference = $schema['$ref'];
        unset($schema['$ref']);

        return array_replace($this->definition($reference), $schema);
    }

    /** @return array<string, mixed> */
    private function definition(string $name): array
    {
        $definition = $this->manifest()['definitions'][$name] ?? null;

        if (! is_array($definition)) {
            throw new RuntimeException("Unknown CMS contract definition [{$name}].");
        }

        return $definition;
    }

    private function path(string $base, string $field): string
    {
        return $base === '' ? $field : "{$base}.{$field}";
    }

    /** @return array<string, mixed> */
    private function manifest(): array
    {
        if (self::$cachedManifest !== null) {
            return self::$cachedManifest;
        }

        $contents = file_get_contents(resource_path('cms/page-contract.v1.json'));

        if ($contents === false) {
            throw new RuntimeException('CMS contract manifest is unreadable.');
        }

        try {
            $manifest = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('CMS contract manifest is invalid JSON.', previous: $exception);
        }

        if (! is_array($manifest) || ($manifest['version'] ?? null) !== PageDocumentContract::VERSION) {
            throw new RuntimeException('CMS contract manifest does not match the current schema version.');
        }

        return self::$cachedManifest = $manifest;
    }
}
