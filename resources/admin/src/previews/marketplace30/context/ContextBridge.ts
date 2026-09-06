import {createContext, useContext} from 'react';
import type {usePreviewModel} from './AdminContext';

// Type-only reference keeps demo fixtures out of operational entry bundles.
export const PreviewContext = createContext<ReturnType<typeof usePreviewModel> | null>(null);
export function useOptionalAdmin() { return useContext(PreviewContext); }
export function useAdmin() {
  const value = useOptionalAdmin();
  if (!value) throw new Error('Preview provider required');
  return value;
}
