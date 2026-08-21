<?php

namespace App\Domain\Cms;

use stdClass;

final class VisiblePageRuntimePayload
{
    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function filter(array $payload): array
    {
        $sections = $payload['page']['document']['sections'] ?? [];
        $runtimeSections = $payload['runtime']['sections'] ?? [];
        $runtimeErrors = $payload['runtime']['errors'] ?? [];
        $visibleSections = [];
        $visibleRuntime = [];
        $visibleErrors = [];
        $indexMap = [];

        foreach ($sections as $index => $section) {
            if (! is_array($section) || ($section['isVisible'] ?? false) !== true) {
                continue;
            }

            $newIndex = count($visibleSections);
            $indexMap[(string) $index] = $newIndex;
            $visibleSections[] = $section;

            if (is_array($runtimeSections) && array_key_exists($index, $runtimeSections)) {
                $visibleRuntime[$newIndex] = $runtimeSections[$index];
            }
        }

        if (is_array($runtimeErrors)) {
            foreach ($runtimeErrors as $path => $error) {
                if (! is_string($path) || preg_match('/\Asections\.([0-9]+)(\..+)\z/D', $path, $matches) !== 1) {
                    $visibleErrors[$path] = $error;

                    continue;
                }

                $newIndex = $indexMap[$matches[1]] ?? null;
                if ($newIndex !== null) {
                    $visibleErrors['sections.'.$newIndex.$matches[2]] = $error;
                }
            }
        }

        $payload['page']['document']['sections'] = $visibleSections;
        $payload['runtime']['sections'] = $visibleRuntime === [] ? new stdClass : (object) $visibleRuntime;
        $payload['runtime']['errors'] = $visibleErrors === [] ? new stdClass : (object) $visibleErrors;

        return $payload;
    }
}
