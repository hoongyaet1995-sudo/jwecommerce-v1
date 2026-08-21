import React from 'react';
import { createRoot } from 'react-dom/client';
import { AdminProvider, useAdmin } from '../../src/context/AdminContext';

const SENTINEL_ID = 'route-state-memory-sentinel';

function StateProbe() {
  const { currentSection, products, saveProduct, setCurrentSection } = useAdmin();
  const sentinel = products.find((product) => product.id === SENTINEL_ID);

  return (
    <main>
      <output data-testid="section">{currentSection}</output>
      <output data-testid="sentinel">{sentinel ? 'present' : 'missing'}</output>
      <button onClick={() => saveProduct({ ...products[0], id: SENTINEL_ID, title: 'Memory-only route sentinel' })}>
        Seed memory sentinel
      </button>
      <button onClick={() => localStorage.removeItem('aurora_admin_products')}>Clear persisted products</button>
      <button onClick={() => setCurrentSection('commerce')}>Navigate to Commerce</button>
    </main>
  );
}

createRoot(document.getElementById('root')!).render(<AdminProvider><StateProbe /></AdminProvider>);
