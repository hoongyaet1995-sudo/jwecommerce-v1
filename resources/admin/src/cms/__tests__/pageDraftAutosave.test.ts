import { afterEach, describe, expect, it, vi } from 'vitest';
import { PageDraftAutosave } from '../pageDraftAutosave';

type TestDocument = {
  title: string;
};

const doc = (title: string): TestDocument => ({ title });

const deferred = <T,>() => {
  let resolve!: (value: T) => void;
  let reject!: (reason?: unknown) => void;
  const promise = new Promise<T>((resolvePromise, rejectPromise) => {
    resolve = resolvePromise;
    reject = rejectPromise;
  });

  return { promise, resolve, reject };
};

describe('PageDraftAutosave', () => {
  afterEach(() => {
    vi.useRealTimers();
  });

  it('collapses rapid edits into one save containing the latest document', async () => {
    vi.useFakeTimers();
    const save = vi.fn().mockResolvedValue({ lockVersion: 2 });
    const controller = new PageDraftAutosave<TestDocument>({ delayMs: 600, lockVersion: 1, save });

    controller.schedule(doc('A'));
    controller.schedule(doc('AB'));
    controller.schedule(doc('ABC'));
    await vi.advanceTimersByTimeAsync(599);
    expect(save).not.toHaveBeenCalled();

    await vi.advanceTimersByTimeAsync(1);
    expect(save).toHaveBeenCalledTimes(1);
    expect(save).toHaveBeenCalledWith(doc('ABC'), 1);
  });

  it('flushes immediately and resolves only after every newer queued edit is acknowledged', async () => {
    vi.useFakeTimers();
    const first = deferred<{ lockVersion: number }>();
    const second = deferred<{ lockVersion: number }>();
    const save = vi.fn()
      .mockReturnValueOnce(first.promise)
      .mockReturnValueOnce(second.promise);
    const controller = new PageDraftAutosave<TestDocument>({ delayMs: 600, lockVersion: 1, save });

    controller.schedule(doc('A'));
    let flushResolved = false;
    const flush = controller.flush().then(() => { flushResolved = true; });
    expect(save).toHaveBeenCalledWith(doc('A'), 1);

    controller.schedule(doc('AB'));
    first.resolve({ lockVersion: 2 });
    await vi.waitFor(() => expect(save).toHaveBeenCalledTimes(2));
    expect(save).toHaveBeenLastCalledWith(doc('AB'), 2);
    expect(flushResolved).toBe(false);

    second.resolve({ lockVersion: 3 });
    await flush;
    expect(flushResolved).toBe(true);
    expect(controller.getState()).toMatchObject({
      dirty: false,
      saving: false,
      acknowledgedVersion: 3,
      conflict: null,
    });
  });

  it('retains the latest dirty document after failure and rejects flush', async () => {
    vi.useFakeTimers();
    const failure = new Error('network unavailable');
    const save = vi.fn()
      .mockRejectedValueOnce(failure)
      .mockResolvedValueOnce({ lockVersion: 2 });
    const controller = new PageDraftAutosave<TestDocument>({ delayMs: 600, lockVersion: 1, save });

    controller.schedule(doc('A'));
    await expect(controller.flush()).rejects.toBe(failure);
    expect(controller.getState()).toMatchObject({ dirty: true, saving: false, acknowledgedVersion: 1 });

    controller.schedule(doc('latest local value'));
    await controller.flush();
    expect(save).toHaveBeenLastCalledWith(doc('latest local value'), 1);
    expect(controller.getState().dirty).toBe(false);
  });

  it('never overlaps saves and collapses in-flight edits to the latest full document', async () => {
    vi.useFakeTimers();
    const first = deferred<{ lockVersion: number }>();
    const second = deferred<{ lockVersion: number }>();
    let activeRequests = 0;
    let maximumActiveRequests = 0;
    const save = vi.fn(async (_document: TestDocument, _version: number) => {
      activeRequests += 1;
      maximumActiveRequests = Math.max(maximumActiveRequests, activeRequests);
      const result = save.mock.calls.length === 1 ? await first.promise : await second.promise;
      activeRequests -= 1;
      return result;
    });
    const controller = new PageDraftAutosave<TestDocument>({ delayMs: 600, lockVersion: 1, save });

    controller.schedule(doc('A'));
    await vi.advanceTimersByTimeAsync(600);
    controller.schedule(doc('AB'));
    controller.schedule(doc('ABC'));
    await vi.advanceTimersByTimeAsync(600);
    expect(save).toHaveBeenCalledTimes(1);

    first.resolve({ lockVersion: 2 });
    await vi.waitFor(() => expect(save).toHaveBeenCalledTimes(2));
    expect(save).toHaveBeenLastCalledWith(doc('ABC'), 2);
    expect(maximumActiveRequests).toBe(1);

    second.resolve({ lockVersion: 3 });
    await controller.flush();
    expect(maximumActiveRequests).toBe(1);
    expect(controller.getState().acknowledgedVersion).toBe(3);
  });

  it('retains local state and blocks blind automatic retry after a version conflict', async () => {
    vi.useFakeTimers();
    const conflict = Object.assign(new Error('stale draft'), {
      status: 409,
      code: 'cms_version_conflict',
      currentLockVersion: 9,
    });
    const save = vi.fn().mockRejectedValue(conflict);
    const controller = new PageDraftAutosave<TestDocument>({ delayMs: 600, lockVersion: 4, save });

    controller.schedule(doc('local edit'));
    await expect(controller.flush()).rejects.toBe(conflict);
    expect(controller.getState()).toMatchObject({
      dirty: true,
      saving: false,
      acknowledgedVersion: 4,
      conflict: { currentLockVersion: 9 },
    });

    controller.schedule(doc('newer local edit'));
    await vi.advanceTimersByTimeAsync(5_000);
    expect(save).toHaveBeenCalledTimes(1);
    await expect(controller.flush()).rejects.toBe(conflict);
    expect(save).toHaveBeenCalledTimes(1);
  });

  it('disposes timers and subscriptions without emitting after an in-flight save settles', async () => {
    vi.useFakeTimers();
    const pending = deferred<{ lockVersion: number }>();
    const save = vi.fn().mockReturnValue(pending.promise);
    const controller = new PageDraftAutosave<TestDocument>({ delayMs: 600, lockVersion: 1, save });
    const states: Array<ReturnType<typeof controller.getState>> = [];
    controller.subscribe(state => states.push(state));

    controller.schedule(doc('A'));
    await vi.advanceTimersByTimeAsync(600);
    expect(states.every(state => Object.isFrozen(state))).toBe(true);
    const emissionCountAtDispose = states.length;

    controller.dispose();
    pending.resolve({ lockVersion: 2 });
    await Promise.resolve();
    await Promise.resolve();
    await vi.advanceTimersByTimeAsync(5_000);

    expect(states).toHaveLength(emissionCountAtDispose);
    expect(save).toHaveBeenCalledTimes(1);
    expect(() => controller.schedule(doc('ignored'))).toThrow('disposed');
  });
});
