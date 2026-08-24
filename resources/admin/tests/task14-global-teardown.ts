import { execFileSync } from 'node:child_process';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { connect } from 'node:net';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const testDirectory = fileURLToPath(new URL('.', import.meta.url));
const repositoryRoot = resolve(testDirectory, '../../..');
const evidenceDirectory = resolve(repositoryRoot, 'test-results/task-14/pre-cutover');
const harness = resolve(testDirectory, 'fixtures/task14-live-harness.php');
const runtimeEvidencePath = resolve(evidenceDirectory, 'owned-runtime-evidence.json');

const portHasListener = (): Promise<boolean> => new Promise(resolvePort => {
  const socket = connect({host: '127.0.0.1', port: 8500});
  socket.once('connect', () => { socket.destroy(); resolvePort(true); });
  socket.once('error', () => { socket.destroy(); resolvePort(false); });
  socket.setTimeout(1000, () => { socket.destroy(); resolvePort(false); });
});

export default async (): Promise<void> => {
  mkdirSync(evidenceDirectory, { recursive: true });
  let cleanupFailure: Error | null = null;
  try {
    if (process.env.CMS_ACCEPTANCE_TOKEN) {
      const raw = execFileSync('C:\\xampp\\php\\php.exe', [harness, 'cleanup'], {
        cwd: repositoryRoot,
        env: { ...process.env, APP_URL: 'http://127.0.0.1:8500' },
        encoding: 'utf8',
      });
      const evidence = JSON.parse(raw) as {
        baselineMatches?: boolean;
        ownedRemaining?: Record<string, number>;
        after?: {counts?: Record<string, number>; rowDigests?: Record<string, string>};
      };
      writeFileSync(resolve(evidenceDirectory, 'cleanup-safety-evidence.json'), `${JSON.stringify(evidence, null, 2)}\n`);
      const setup = JSON.parse(readFileSync(resolve(evidenceDirectory, 'setup-evidence.json'), 'utf8')) as {
        before?: {counts?: Record<string, number>; rowDigests?: Record<string, string>};
      };
      const exactCountsMatch = JSON.stringify(evidence.after?.counts) === JSON.stringify(setup.before?.counts);
      const exactRowDigestsMatch = JSON.stringify(evidence.after?.rowDigests) === JSON.stringify(setup.before?.rowDigests);
      if (
        evidence.baselineMatches !== true
        || !exactCountsMatch
        || !exactRowDigestsMatch
        || !Object.values(evidence.ownedRemaining ?? {}).every(count => count === 0)
      ) {
        cleanupFailure = new Error('Task 14 teardown did not restore the exact keyed-row baseline.');
      }
    }
  } finally {
    const pid = Number(process.env.CMS_ACCEPTANCE_RUNTIME_PID ?? 0);
    if (Number.isInteger(pid) && pid > 0) {
      try { process.kill(pid); } catch {}
    }
    for (let attempt = 0; attempt < 20 && await portHasListener(); attempt += 1) {
      await new Promise(resolveWait => setTimeout(resolveWait, 100));
    }
    const listenerAfterStop = await portHasListener();
    if (process.env.CMS_ACCEPTANCE_RUNTIME_PID) {
      const runtime = JSON.parse(readFileSync(runtimeEvidencePath, 'utf8')) as Record<string, unknown>;
      writeFileSync(runtimeEvidencePath, `${JSON.stringify({
        ...runtime,
        status: listenerAfterStop ? 'stop-failed-or-foreign-listener-present' : 'stopped',
        stoppedAt: new Date().toISOString(),
        ownedServerPidAbsent: (() => {
          try { process.kill(Number(process.env.CMS_ACCEPTANCE_RUNTIME_PID), 0); return false; } catch { return true; }
        })(),
        portClearAfterStop: !listenerAfterStop,
      }, null, 2)}\n`);
    }
    if (listenerAfterStop && cleanupFailure === null) {
      cleanupFailure = new Error('Port 8500 is not clear after stopping the exact owned Task 14 runtime.');
    }
  }
  if (cleanupFailure) throw cleanupFailure;
};
