export interface PageDraftAutosaveConflict {
  currentLockVersion: number;
  message: string;
}

export interface PageDraftAutosaveState {
  dirty: boolean;
  saving: boolean;
  acknowledgedVersion: number;
  conflict: PageDraftAutosaveConflict | null;
}

interface SaveResult {
  lockVersion: number;
}

interface PageDraftAutosaveOptions<Document> {
  delayMs: number;
  lockVersion: number;
  save: (document: Document, lockVersion: number) => Promise<SaveResult>;
}

type StateListener = (state: Readonly<PageDraftAutosaveState>) => void;

type ConflictLike = Error & {
  status?: number;
  code?: string;
  currentLockVersion?: number;
};

const frozenState = (
  state: PageDraftAutosaveState,
): Readonly<PageDraftAutosaveState> => Object.freeze({
  ...state,
  conflict: state.conflict ? Object.freeze({ ...state.conflict }) : null,
});

const conflictFrom = (error: unknown): PageDraftAutosaveConflict | null => {
  if (!(error instanceof Error)) return null;

  const candidate = error as ConflictLike;
  if (
    candidate.status !== 409
    && candidate.code !== 'cms_version_conflict'
  ) return null;

  if (typeof candidate.currentLockVersion !== 'number') return null;

  return {
    currentLockVersion: candidate.currentLockVersion,
    message: candidate.message,
  };
};

export class PageDraftAutosave<Document> {
  private readonly delayMs: number;
  private readonly saveDraft: PageDraftAutosaveOptions<Document>['save'];
  private readonly listeners = new Set<StateListener>();
  private state: Readonly<PageDraftAutosaveState>;
  private latestDocument: Document | null = null;
  private latestRevision = 0;
  private acknowledgedRevision = 0;
  private timer: ReturnType<typeof setTimeout> | null = null;
  private inFlight: Promise<void> | null = null;
  private timerElapsedWhileSaving = false;
  private conflictError: unknown = null;
  private disposed = false;

  constructor(options: PageDraftAutosaveOptions<Document>) {
    this.delayMs = options.delayMs;
    this.saveDraft = options.save;
    this.state = frozenState({
      dirty: false,
      saving: false,
      acknowledgedVersion: options.lockVersion,
      conflict: null,
    });
  }

  getState = (): Readonly<PageDraftAutosaveState> => this.state;

  subscribe = (listener: StateListener): (() => void) => {
    this.assertActive();
    this.listeners.add(listener);
    listener(this.state);

    return () => {
      this.listeners.delete(listener);
    };
  };

  schedule = (document: Document): void => {
    this.assertActive();
    this.latestDocument = document;
    this.latestRevision += 1;
    this.updateState({ dirty: true });
    this.clearTimer();

    if (this.conflictError) return;

    this.timer = setTimeout(() => {
      this.timer = null;
      this.runAutomaticSave();
    }, this.delayMs);
  };

  flush = async (): Promise<void> => {
    this.assertActive();
    this.clearTimer();
    this.timerElapsedWhileSaving = false;

    while (this.latestRevision > this.acknowledgedRevision) {
      this.assertActive();
      if (this.conflictError) throw this.conflictError;

      if (this.inFlight) {
        await this.inFlight;
        continue;
      }

      await this.startSave();
    }
  };

  dispose = (): void => {
    if (this.disposed) return;
    this.disposed = true;
    this.clearTimer();
    this.timerElapsedWhileSaving = false;
    this.listeners.clear();
  };

  private runAutomaticSave = (): void => {
    if (this.disposed || this.conflictError || !this.state.dirty) return;

    if (this.inFlight) {
      this.timerElapsedWhileSaving = true;
      return;
    }

    void this.startSave().catch(() => undefined);
  };

  private startSave = (): Promise<void> => {
    if (this.inFlight) return this.inFlight;
    if (this.latestDocument === null) return Promise.resolve();

    const document = this.latestDocument;
    const revision = this.latestRevision;
    const version = this.state.acknowledgedVersion;
    this.updateState({ saving: true });

    const request = (async () => {
      try {
        const result = await this.saveDraft(document, version);
        if (this.disposed) return;

        this.acknowledgedRevision = revision;
        this.conflictError = null;
        this.updateState({
          saving: false,
          dirty: this.latestRevision > this.acknowledgedRevision,
          acknowledgedVersion: result.lockVersion,
          conflict: null,
        });
      } catch (error) {
        if (!this.disposed) {
          const conflict = conflictFrom(error);
          if (conflict) this.conflictError = error;
          this.clearTimer();
          this.timerElapsedWhileSaving = false;
          this.updateState({
            saving: false,
            dirty: true,
            conflict,
          });
        }
        throw error;
      } finally {
        this.inFlight = null;
        if (
          !this.disposed
          && !this.conflictError
          && this.timerElapsedWhileSaving
          && this.latestRevision > this.acknowledgedRevision
        ) {
          this.timerElapsedWhileSaving = false;
          this.runAutomaticSave();
        }
      }
    })();

    this.inFlight = request;
    return request;
  };

  private clearTimer = (): void => {
    if (this.timer === null) return;
    clearTimeout(this.timer);
    this.timer = null;
  };

  private updateState = (patch: Partial<PageDraftAutosaveState>): void => {
    if (this.disposed) return;
    this.state = frozenState({ ...this.state, ...patch });
    this.listeners.forEach(listener => listener(this.state));
  };

  private assertActive = (): void => {
    if (this.disposed) throw new Error('PageDraftAutosave has been disposed.');
  };
}
