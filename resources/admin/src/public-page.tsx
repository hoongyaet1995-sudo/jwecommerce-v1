import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import {
  PageRuntimeProvider,
  PageRuntimeUnavailable,
  PageSections,
  readPageRuntimeBootstrap,
} from './cms/PageRuntimeContext';
import './index.css';

const root = document.getElementById('cms-page-root');

if (root) {
  const payload = readPageRuntimeBootstrap();
  createRoot(root).render(
    <StrictMode>
      {payload ? (
        <PageRuntimeProvider payload={payload}>
          <PageSections sections={payload.page.document.sections} mode={payload.context.mode} />
        </PageRuntimeProvider>
      ) : <PageRuntimeUnavailable />}
    </StrictMode>,
  );
}
