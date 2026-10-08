<?php

namespace App\Domain\Cms;

use App\Domain\Cms\Exceptions\CmsRuntimeReferenceInvalid;
use Illuminate\Validation\ValidationException;
use JsonException;
use RuntimeException;
use stdClass;

final class PageDocumentContract
{
    public const VERSION = 1;

    /** @var array<string, mixed>|null */
    private static ?array $cachedManifest = null;

    /** @param array<string, mixed>|null $manifestOverride */
    public function __construct(private readonly ?array $manifestOverride = null) {}

    /**
     * Validate the complete document without filtering or normalizing its values.
     *
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(array $document): array
    {
        $errors = [];

        $this->validateValue($document, $this->manifest()['document'], '', $errors);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $document;
    }

    /**
     * Validate a document for promotion without changing its draft representation.
     *
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $runtimeFacts
     *
     * @throws ValidationException
     */
    public function assertPublishable(array $document, array $runtimeFacts): void
    {
        $this->validate($document);

        $errors = [];
        $sectionTypes = $this->manifest()['section']['sectionTypes'];

        foreach ($document['sections'] as $index => $section) {
            if ($section['isVisible'] === false) {
                continue;
            }

            $sectionPath = "sections.{$index}";
            $sectionDefinition = $sectionTypes[$section['type']];

            if (($sectionDefinition['renderer']['capable'] ?? false) !== true) {
                $this->addError($errors, "{$sectionPath}.type", 'This visible section type cannot be rendered.');
            }

            foreach ($sectionDefinition['publishRequired'] as $field) {
                if (! array_key_exists($field, $section) || ! $this->hasPublishValue($section[$field])) {
                    $this->addError($errors, "{$sectionPath}.{$field}", 'This visible section field is required to publish.');
                }
            }

        }

        $runtimeErrors = $this->runtimeErrors($document, $runtimeFacts);

        foreach ($runtimeErrors as $path => $messages) {
            foreach ($messages as $message) {
                $this->addError($errors, $path, $message);
            }
        }

        if ($runtimeErrors !== []) {
            throw CmsRuntimeReferenceInvalid::withMessages($errors);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Report invalid visible runtime references without making a draft unreadable.
     *
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $runtimeFacts
     * @return array<string, list<string>>
     */
    public function runtimeErrors(array $document, array $runtimeFacts): array
    {
        $errors = [];
        $sectionTypes = $this->manifest()['section']['sectionTypes'];

        foreach ($document['sections'] ?? [] as $index => $section) {
            if (! is_array($section) || ($section['isVisible'] ?? false) !== true) {
                continue;
            }

            $sectionDefinition = $sectionTypes[$section['type'] ?? ''] ?? null;

            if (! is_array($sectionDefinition)) {
                continue;
            }

            foreach ($sectionDefinition['runtimeRequirements'] ?? [] as $requirement) {
                $factPattern = is_array($requirement) ? ($requirement['fact'] ?? '') : $requirement;
                $fieldPattern = is_array($requirement) ? ($requirement['field'] ?? 'type') : 'type';

                if (! is_string($factPattern) || $factPattern === '' || ! is_string($fieldPattern)) {
                    continue;
                }

                $fields = str_contains($fieldPattern, '*')
                    ? $this->expandRuntimeFields($section, explode('.', $fieldPattern))
                    : [['path' => $fieldPattern, 'wildcards' => []]];

                foreach ($fields as $field) {
                    $factPath = $this->replaceWildcards($factPattern, $field['wildcards']);
                    $factPath = str_starts_with($factPath, 'sections.')
                        ? $factPath
                        : "sections.{$index}.{$factPath}";
                    $fact = $this->runtimeFact($runtimeFacts, $factPath);

                    if ($fact === null && ! $this->runtimeFieldHasReference($section, $field['path'])) {
                        continue;
                    }

                    if ($fact !== true) {
                        $this->addError(
                            $errors,
                            "sections.{$index}.{$field['path']}",
                            'The runtime reference for this visible section is invalid.',
                        );
                    }
                }
            }
        }

        return $errors;
    }

    /** @param array<string, mixed> $section */
    private function runtimeFieldHasReference(array $section, string $path): bool
    {
        $value = $section;

        foreach (explode('.', $path) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return false;
            }

            $value = $value[$segment];
        }

        return $value !== null && $value !== '' && $value !== [];
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, array<int, string>>  $errors
     */
    private function validateValue(mixed $value, array $schema, string $path, array &$errors): void
    {
        if (($schema['$ref'] ?? null) === 'section') {
            $this->validateSection($value, $path, $errors);

            return;
        }

        $schema = $this->resolveSchema($schema);
        $types = $schema['types'] ?? [$schema['type']];

        if (! $this->matchesAnyType($value, $types)) {
            $this->addError($errors, $path, 'This value has an unsupported JSON type.');

            return;
        }

        if (isset($schema['enum']) && ! in_array($value, $schema['enum'], true)) {
            $this->addError($errors, $path, 'This value is not allowed by the page contract.');
        }

        if (($schema['format'] ?? null) === 'hex_color' && is_string($value) && ! preg_match('/^#[0-9a-f]{6}$/i', $value)) {
            $this->addError($errors, $path, 'Use a six-digit hexadecimal color.');
        }

        if (($schema['format'] ?? null) === 'managed_or_external_url' && is_string($value)) {
            if (str_starts_with(strtolower($value), 'data:')) {
                $this->addError($errors, $path, 'Data URLs are not allowed in page documents.');
            } elseif (! MediaUrlPolicy::isSafe($value)) {
                $this->addError(
                    $errors,
                    $path,
                    'Media must use a managed asset URL or a safe public HTTPS URL.',
                );
            }
        }

        if (($schema['type'] ?? null) === 'array') {
            foreach ($value as $index => $item) {
                $this->validateValue($item, $schema['items'], $this->path($path, (string) $index), $errors);
            }
        }

        if (($schema['type'] ?? null) === 'object' && isset($schema['fields'])) {
            $this->validateObject($value, $schema, $path, $errors);
        }
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, array<int, string>>  $errors
     */
    private function validateSection(mixed $value, string $path, array &$errors): void
    {
        if (! $this->isObject($value)) {
            $this->addError($errors, $path, 'This value must be a JSON object.');

            return;
        }

        $value = $value instanceof stdClass ? get_object_vars($value) : $value;

        if (! array_key_exists('type', $value) || ! is_string($value['type'])) {
            $this->addError($errors, $this->path($path, 'type'), 'A supported section type is required.');

            return;
        }

        $sectionTypes = $this->manifest()['section']['sectionTypes'];

        if (! array_key_exists($value['type'], $sectionTypes)) {
            $this->addError($errors, $this->path($path, 'type'), 'This section type is not supported by the page contract.');

            return;
        }

        $fields = [];

        foreach ($this->manifest()['section']['commonFields'] as $field) {
            $fields[$field] = array_replace(
                $this->definition($field),
                ['required' => true],
            );
        }

        foreach ($sectionTypes[$value['type']]['fields'] as $field) {
            $fields[$field] = $this->definition($field);
        }

        $this->validateObject($value, [
            'type' => 'object',
            'allowUnknownFields' => false,
            'fields' => $fields,
        ], $path, $errors);

        $this->validateImageSlots($value, $path, $errors);
    }

    private function validateImageSlots(array $section, string $path, array &$errors): void
    {
        if (! isset($section['imageSlots']) || ! is_array($section['imageSlots'])) {
            return;
        }
        $type = $section['type'];
        $targets = [];
        if ((($type === 'chapter_block' && ($section['chapterPreset'] ?? 'story') === 'story') || in_array($type, ['cta_strip', 'hero_banner', 'split_hero', 'media_text_split', 'featured_deal'], true))
            && ($section['mediaType'] ?? 'image') !== 'video' && ! ($type === 'cta_strip' && ($section['ctaPreset'] ?? 'default') === 'editorial-band')) {
            $targets['mediaUrl'] = in_array($type, ['hero_banner', 'cta_strip'], true);
        } elseif ($type === 'before_after') {
            $targets = ['beforeImageUrl' => true, 'afterImageUrl' => true];
        } elseif (($type === 'chapter_block' && ($section['chapterPreset'] ?? 'story') === 'perspective') || in_array($type, ['hero_slider', 'media_gallery', 'instagram_feed', 'testimonials_slider', 'logo_cloud'], true)) {
            $field = $type === 'testimonials_slider' ? 'authorAvatar' : 'imageUrl';
            foreach ($section['items'] ?? [] as $item) {
                $item = $item instanceof stdClass ? get_object_vars($item) : $item;
                if (! is_array($item) || ! isset($item['id']) || ! is_string($item['id'])) {
                    continue;
                }
                if ($type === 'hero_slider' && ! empty($item['videoUrl'])) {
                    continue;
                }
                $targets['item:'.$item['id'].':'.$field] = in_array($type, ['hero_slider', 'testimonials_slider', 'logo_cloud'], true);
            }
        }
        $seen = [];
        foreach ($section['imageSlots'] as $index => $slot) {
            $slot = $slot instanceof stdClass ? get_object_vars($slot) : $slot;
            if (! is_array($slot) || ! isset($slot['target']) || ! is_string($slot['target'])) {
                continue;
            }
            $slotPath = $path.'.imageSlots.'.$index;
            if (! array_key_exists($slot['target'], $targets) || isset($seen[$slot['target']])) {
                $this->addError($errors, $slotPath.'.target', 'Choose a unique image target belonging to this section.');
                continue;
            }
            $seen[$slot['target']] = true;
            if ($targets[$slot['target']]) {
                foreach (['imageRatio' => 'default', 'imageWidth' => '100', 'imageShape' => 'default'] as $field => $default) {
                    if (isset($slot[$field]) && $slot[$field] !== $default) {
                        $this->addError($errors, $slotPath.'.'.$field, 'Image geometry is owned by this section layout.');
                    }
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, array<int, string>>  $errors
     */
    private function validateObject(array|stdClass $value, array $schema, string $path, array &$errors): void
    {
        $value = $value instanceof stdClass ? get_object_vars($value) : $value;
        $fields = $schema['fields'];

        if (($schema['allowUnknownFields'] ?? false) === false) {
            foreach ($value as $field => $_) {
                if (! array_key_exists($field, $fields)) {
                    $this->addError($errors, $this->path($path, (string) $field), 'This field is not supported by the page contract.');
                }
            }
        }

        foreach ($fields as $field => $fieldSchema) {
            $resolvedSchema = $this->resolveSchema($fieldSchema);

            if (! array_key_exists($field, $value)) {
                if (($resolvedSchema['required'] ?? false) === true) {
                    $this->addError($errors, $this->path($path, $field), 'This contract field is required.');
                }

                continue;
            }

            $this->validateValue($value[$field], $fieldSchema, $this->path($path, $field), $errors);
        }
    }

    /** @param array<string, mixed> $schema */
    private function resolveSchema(array $schema): array
    {
        if (! isset($schema['$ref'])) {
            return $schema;
        }

        $reference = $schema['$ref'];
        $localSchema = $schema;
        unset($localSchema['$ref']);

        return array_replace($this->definition($reference), $localSchema);
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

    /** @param array<int, string> $types */
    private function matchesAnyType(mixed $value, array $types): bool
    {
        foreach ($types as $type) {
            $matches = match ($type) {
                'string' => is_string($value),
                'number' => is_int($value) || is_float($value),
                'integer' => is_int($value),
                'boolean' => is_bool($value),
                'null' => $value === null,
                'array' => is_array($value) && array_is_list($value),
                'object' => $this->isObject($value),
                default => false,
            };

            if ($matches) {
                return true;
            }
        }

        return false;
    }

    private function isObject(mixed $value): bool
    {
        return $value instanceof stdClass
            || (is_array($value) && ($value === [] || ! array_is_list($value)));
    }

    private function hasPublishValue(mixed $value): bool
    {
        return $value !== null && $value !== '';
    }

    /** @param array<string, mixed> $runtimeFacts */
    private function runtimeFact(array $runtimeFacts, string $path): mixed
    {
        $value = $runtimeFacts;

        foreach (explode('.', $path) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * @param  array<int, string>  $segments
     * @param  list<string>  $path
     * @param  list<string>  $wildcards
     * @return list<array{path: string, wildcards: list<string>}>
     */
    private function expandRuntimeFields(
        mixed $value,
        array $segments,
        array $path = [],
        array $wildcards = [],
    ): array {
        if ($segments === []) {
            return [[
                'path' => implode('.', $path),
                'wildcards' => $wildcards,
            ]];
        }

        $segment = array_shift($segments);

        if ($segment === '*') {
            if (! is_array($value)) {
                return [];
            }

            $expanded = [];

            foreach ($value as $index => $nestedValue) {
                $expanded = array_merge($expanded, $this->expandRuntimeFields(
                    $nestedValue,
                    $segments,
                    [...$path, (string) $index],
                    [...$wildcards, (string) $index],
                ));
            }

            return $expanded;
        }

        if (! is_array($value) || ! array_key_exists($segment, $value)) {
            return [];
        }

        return $this->expandRuntimeFields(
            $value[$segment],
            $segments,
            [...$path, $segment],
            $wildcards,
        );
    }

    /** @param list<string> $wildcards */
    private function replaceWildcards(string $pattern, array $wildcards): string
    {
        foreach ($wildcards as $replacement) {
            $position = strpos($pattern, '*');

            if ($position === false) {
                break;
            }

            $pattern = substr_replace($pattern, $replacement, $position, 1);
        }

        return $pattern;
    }

    /** @param array<string, array<int, string>> $errors */
    private function addError(array &$errors, string $path, string $message): void
    {
        $errors[$path][] = $message;
    }

    private function path(string $base, string $key): string
    {
        return $base === '' ? $key : "{$base}.{$key}";
    }

    /** @return array<string, mixed> */
    private function manifest(): array
    {
        if ($this->manifestOverride !== null) {
            if (($this->manifestOverride['version'] ?? null) !== self::VERSION) {
                throw new RuntimeException('CMS contract manifest does not match the current schema version.');
            }

            return $this->manifestOverride;
        }

        if (self::$cachedManifest !== null) {
            return self::$cachedManifest;
        }

        $manifestPath = resource_path('cms/page-contract.v1.json');
        $contents = file_get_contents($manifestPath);

        if ($contents === false) {
            throw new RuntimeException("CMS contract manifest is unreadable at [{$manifestPath}].");
        }

        try {
            $manifest = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('CMS contract manifest is invalid JSON.', previous: $exception);
        }

        if (! is_array($manifest) || ($manifest['version'] ?? null) !== self::VERSION) {
            throw new RuntimeException('CMS contract manifest does not match the current schema version.');
        }

        return self::$cachedManifest = $manifest;
    }
}
