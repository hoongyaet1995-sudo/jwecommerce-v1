import React from 'react';
import { createRoot } from 'react-dom/client';
import { AdminProvider } from '../../src/context/AdminContext';
import { PageAdmin } from '../../src/components/pages/PageAdmin';

createRoot(document.getElementById('root')!).render(
  <AdminProvider>
    <PageAdmin />
  </AdminProvider>,
);
