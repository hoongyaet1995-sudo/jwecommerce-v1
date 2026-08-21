import { rmSync } from 'node:fs';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const testDirectory = fileURLToPath(new URL('.', import.meta.url));
const repositoryRoot = resolve(testDirectory, '../../..');

export default async () => {
  rmSync(resolve(repositoryRoot, 'test-results/task-10/task10-browser.sqlite'), { force: true });
};
