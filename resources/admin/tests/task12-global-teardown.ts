import { rmSync } from 'node:fs';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const testDirectory = fileURLToPath(new URL('.', import.meta.url));
export default async () => rmSync(resolve(testDirectory, '../../../test-results/task-12/task12-browser.sqlite'), {force: true});
