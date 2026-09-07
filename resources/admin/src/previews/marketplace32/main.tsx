import React, {useState} from 'react';
import {createRoot} from 'react-dom/client';
import {JourneyProvider, type MarketplaceJourneyContext} from './context/AdminContext';
import {ImportProductReviewModal} from './components/marketplace/ImportProductReviewModal';
import {ProductMappingModal} from './components/marketplace/ProductMappingModal';
import {PublishProductModal} from './components/marketplace/PublishProductModal';
import {ConfirmSuggestionsModal} from './components/marketplace/ConfirmSuggestionsModal';
import {INITIAL_PRODUCTS, INITIAL_CATEGORIES, INITIAL_MARKETPLACE_CONNECTIONS, INITIAL_MARKETPLACE_STORE_PRODUCTS} from './fixtures';
import './source.css';

function Preview() {
  const [active, setActive] = useState('import');
  const [event, setEvent] = useState('No preview action submitted.');
  const record = (action: string) => setEvent(`SIMULATION ONLY: ${action}. No server request was sent.`);
  const value: MarketplaceJourneyContext = {
    products: INITIAL_PRODUCTS, categories: INITIAL_CATEGORIES,
    connections: INITIAL_MARKETPLACE_CONNECTIONS, marketplaceStoreProducts: INITIAL_MARKETPLACE_STORE_PRODUCTS,
    setActiveSection: section => record(`Navigate ${section}`), addToast: toast => record(toast.title),
    importStoreProductToMaster: () => record('Import request'),
    publishMasterProductToStore: () => record('Publish request'),
    mapStoreVariantToJwSku: () => record('Mapping request'), unmapStoreVariant: () => record('Unlink request'),
  };
  const product = INITIAL_MARKETPLACE_STORE_PRODUCTS[0];
  const close = () => setActive('');
  return <JourneyProvider value={value}>
    <aside style={{padding:12,background:'#fff3cd',color:'#332701',font:'14px sans-serif'}}>
      LOCAL ZIP 32 VISUAL PREVIEW — sample data and simulated results only. No persistence or platform requests.
      <div>{['import','mapping','publish','ai'].map(name => <button key={name} onClick={()=>setActive(name)} style={{margin:8,padding:8}}>{name}</button>)}</div>
      <p>{event}</p>
    </aside>
    <ImportProductReviewModal key={`import-${active}`} isOpen={active==='import'} onClose={close} productsToImport={[product]} />
    <ProductMappingModal key={`mapping-${active}`} isOpen={active==='mapping'} onClose={close} storeProduct={product} storeVariant={product.variants[product.variants.length-1]} />
    <PublishProductModal key={`publish-${active}`} isOpen={active==='publish'} onClose={close} />
    <ConfirmSuggestionsModal key={`ai-${active}`} isOpen={active==='ai'} onClose={close} productsToAccept={INITIAL_MARKETPLACE_STORE_PRODUCTS} />
  </JourneyProvider>;
}
createRoot(document.getElementById('root')!).render(<Preview />);
