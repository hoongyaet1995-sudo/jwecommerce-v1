import React, { useCallback, useEffect } from 'react';
import ReactDOM from 'react-dom/client';

import { ThemeProvider } from '@mui/material';

import App from './App';
import { EditorConfigurationSchema, TEditorConfiguration } from './documents/editor/schema';
import {
  resetDocument,
  setSelectedMainTab,
  useDocument,
} from './documents/editor/EditorContext';
import { renderToStaticMarkup } from './documents/reader/core';
import theme from './theme';
import './editor.css';

type TXeniboxEditorWindow = Window & {
  xeniboxEmailEditorConfig?: {
    initialDocument?: unknown;
    jsonFieldId?: string;
    htmlFieldId?: string;
  };
};

function synchronizeEditor(documentValue: TEditorConfiguration) {
  const configuration = (window as TXeniboxEditorWindow).xeniboxEmailEditorConfig ?? {};
  const jsonField = document.getElementById(configuration.jsonFieldId ?? 'template-editor-json') as HTMLTextAreaElement | null;
  const htmlField = document.getElementById(configuration.htmlFieldId ?? 'template-content-html') as HTMLTextAreaElement | null;

  if (!jsonField || !htmlField) {
    return;
  }

  try {
    jsonField.value = JSON.stringify(documentValue);
    htmlField.value = renderToStaticMarkup(documentValue, { rootBlockId: 'root' });
    window.dispatchEvent(new CustomEvent('xenibox:email-editor-synced'));
  } catch (error) {
    window.dispatchEvent(new CustomEvent('xenibox:email-editor-error'));
  }
}

function EditorBridge() {
  const documentValue = useDocument();
  const synchronize = useCallback(() => synchronizeEditor(documentValue), [documentValue]);

  useEffect(() => {
    synchronize();
  }, [synchronize]);

  useEffect(() => {
    window.dispatchEvent(new CustomEvent('xenibox:email-editor-ready'));
  }, []);

  useEffect(() => {
    window.addEventListener('xenibox:email-editor-request-sync', synchronize);
    return () => window.removeEventListener('xenibox:email-editor-request-sync', synchronize);
  }, [synchronize]);

  useEffect(() => {
    const applyStarterTemplate = (event: Event) => {
      const starterDocument = (event as CustomEvent<{ document?: unknown }>).detail?.document;
      const parsedDocument = EditorConfigurationSchema.safeParse(starterDocument);

      if (!parsedDocument.success) {
        window.dispatchEvent(new CustomEvent('xenibox:starter-template-error'));
        return;
      }

      resetDocument(parsedDocument.data);
      setSelectedMainTab('editor');
      window.dispatchEvent(new CustomEvent('xenibox:starter-template-applied'));
    };

    window.addEventListener('xenibox:apply-starter-template', applyStarterTemplate);
    return () => window.removeEventListener('xenibox:apply-starter-template', applyStarterTemplate);
  }, []);

  return null;
}

const root = document.getElementById('xenibox-email-builder');

if (root) {
  ReactDOM.createRoot(root).render(
    <ThemeProvider theme={theme}>
      <App />
      <EditorBridge />
    </ThemeProvider>
  );
}
