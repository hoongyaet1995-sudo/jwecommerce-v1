import { readdirSync } from 'node:fs';
import { extname, relative, resolve } from 'node:path';

export const SUPPORTED_COMPONENT_EXTENSIONS = new Set(['.tsx', '.jsx', '.ts', '.js']);

export function normalizeComponentPath(path) {
  return path.replaceAll('\\', '/').replace(/^(?:\.\/)+/, '').replace(/\/+/g, '/');
}

export function collectComponentPaths(directory, projectRoot) {
  return readdirSync(directory, { withFileTypes: true })
    .flatMap((entry) => {
      const path = resolve(directory, entry.name);

      if (entry.isDirectory()) return collectComponentPaths(path, projectRoot);

      return entry.isFile() && SUPPORTED_COMPONENT_EXTENSIONS.has(extname(entry.name).toLowerCase())
        ? [normalizeComponentPath(relative(projectRoot, path))]
        : [];
    })
    .sort();
}

export function assertInventoryCompleteness(componentPaths, requiredComponentPaths) {
  const normalized = componentPaths.map(normalizeComponentPath).sort();
  const exactPaths = new Set(normalized);
  const caseFolded = new Map();

  for (const path of normalized) {
    const key = path.toLowerCase();
    const existing = caseFolded.get(key);

    if (existing !== undefined && existing !== path) {
      throw new Error(`Component inventory has a case-fold collision: ${existing} and ${path}.`);
    }

    caseFolded.set(key, path);
  }

  const missing = requiredComponentPaths
    .map(normalizeComponentPath)
    .filter((path) => !exactPaths.has(path));

  if (missing.length > 0) {
    throw new Error(`Component inventory is missing required component paths: ${missing.join(', ')}.`);
  }

  return normalized;
}
