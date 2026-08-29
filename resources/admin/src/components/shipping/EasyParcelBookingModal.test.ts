import { describe, expect, it } from 'vitest';
import { createEasyParcelQuoteRequestGuard } from './EasyParcelBookingModal';

const deferred = <T,>() => {
  let resolve!: (value: T) => void;
  const promise = new Promise<T>((settle) => { resolve = settle; });
  return { promise, resolve };
};

describe('EasyParcelBookingModal quote lifecycle', () => {
  it('does not restore a pending quote after package data is edited or the active parcel changes', async () => {
    const guard = createEasyParcelQuoteRequestGuard();
    let activeParcelId = 'parcel-a';
    let visibleQuote: string | null = null;

    const packageEditRequest = guard.begin(activeParcelId);
    const packageEditQuote = deferred<string>();
    const afterPackageEdit = packageEditQuote.promise.then((quote) => {
      if (guard.isCurrent(packageEditRequest, activeParcelId)) visibleQuote = quote;
    });
    guard.invalidate();
    packageEditQuote.resolve('quote-before-package-edit');
    await afterPackageEdit;

    expect(visibleQuote).toBeNull();

    const parcelSwitchRequest = guard.begin(activeParcelId);
    const parcelSwitchQuote = deferred<string>();
    const afterParcelSwitch = parcelSwitchQuote.promise.then((quote) => {
      if (guard.isCurrent(parcelSwitchRequest, activeParcelId)) visibleQuote = quote;
    });
    activeParcelId = 'parcel-b';
    parcelSwitchQuote.resolve('quote-before-parcel-switch');
    await afterParcelSwitch;

    expect(visibleQuote).toBeNull();
  });
});
