<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use JsonException;

final class ViteManifestEntry
{
    public function __construct(
        private readonly ?string $manifestPath = null,
        private readonly ?string $buildDirectory = null,
    ) {}

    /** @return array{file: string, css: list<string>, imports: list<string>}|null */
    public function resolve(string $source): ?array
    {
        $manifestPath = $this->manifestPath ?? public_path('build-admin/manifest.json');
        $buildDirectory = $this->buildDirectory ?? public_path('build-admin');
        $canonicalBuildDirectory = realpath($buildDirectory);

        if (! $this->isSafePath($source)
            || $canonicalBuildDirectory === false
            || ! is_dir($canonicalBuildDirectory)
            || ! is_readable($canonicalBuildDirectory)
            || ! File::isFile($manifestPath)
            || ! File::isReadable($manifestPath)) {
            return null;
        }

        try {
            $manifest = json_decode(File::get($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($manifest) || array_is_list($manifest) || ! isset($manifest[$source])) {
            return null;
        }

        $resolved = $this->resolveManifestEntry($manifest, $source, $canonicalBuildDirectory, []);

        if ($resolved === null) {
            return null;
        }

        return [
            'file' => $resolved['file'],
            'css' => array_values(array_unique($resolved['css'])),
            'imports' => array_values(array_unique($resolved['imports'])),
        ];
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  list<string>  $ancestors
     * @return array{file: string, css: list<string>, imports: list<string>}|null
     */
    private function resolveManifestEntry(
        array $manifest,
        string $key,
        string $buildDirectory,
        array $ancestors,
    ): ?array {
        if (in_array($key, $ancestors, true)) {
            return null;
        }

        $entry = $manifest[$key] ?? null;
        if (! is_array($entry) || array_is_list($entry)) {
            return null;
        }

        $file = $entry['file'] ?? null;
        $css = $entry['css'] ?? [];
        $imports = $entry['imports'] ?? [];

        if (! is_string($file) || ! is_array($css) || ! array_is_list($css)
            || ! is_array($imports) || ! array_is_list($imports)) {
            return null;
        }

        $resolvedFile = $this->resolveAsset($buildDirectory, $file);
        if ($resolvedFile === null) {
            return null;
        }

        $resolvedCss = [];
        foreach ($css as $asset) {
            if (! is_string($asset)) {
                return null;
            }
            $resolvedAsset = $this->resolveAsset($buildDirectory, $asset);
            if ($resolvedAsset === null) {
                return null;
            }
            $resolvedCss[] = $resolvedAsset;
        }

        $resolvedImports = [];
        $nextAncestors = [...$ancestors, $key];

        foreach ($imports as $importKey) {
            if (! is_string($importKey) || ! $this->isSafePath($importKey)) {
                return null;
            }

            $import = $this->resolveManifestEntry($manifest, $importKey, $buildDirectory, $nextAncestors);
            if ($import === null) {
                return null;
            }

            $resolvedImports[] = $import['file'];
            $resolvedImports = [...$resolvedImports, ...$import['imports']];
            $resolvedCss = [...$resolvedCss, ...$import['css']];
        }

        return [
            'file' => $resolvedFile,
            'css' => $resolvedCss,
            'imports' => $resolvedImports,
        ];
    }

    private function resolveAsset(string $buildDirectory, string $asset): ?string
    {
        if (! $this->isSafePath($asset)) {
            return null;
        }

        $candidate = realpath(
            rtrim($buildDirectory, '/\\')
            .DIRECTORY_SEPARATOR
            .str_replace('/', DIRECTORY_SEPARATOR, $asset),
        );
        if ($candidate === false || ! is_file($candidate) || ! is_readable($candidate)) {
            return null;
        }

        $root = $this->comparablePath($buildDirectory);
        $resolved = $this->comparablePath($candidate);
        if (! str_starts_with($resolved, $root.'/')) {
            return null;
        }

        return substr($this->normalizedPath($candidate), strlen($this->normalizedPath($buildDirectory)) + 1);
    }

    private function comparablePath(string $path): string
    {
        $normalized = $this->normalizedPath($path);

        return DIRECTORY_SEPARATOR === '\\' ? strtolower($normalized) : $normalized;
    }

    private function normalizedPath(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }

    private function isSafePath(string $path): bool
    {
        if ($path === '' || str_contains($path, '\\') || str_starts_with($path, '/')) {
            return false;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return preg_match('/\A[A-Za-z0-9._\/-]+\z/D', $path) === 1;
    }
}
