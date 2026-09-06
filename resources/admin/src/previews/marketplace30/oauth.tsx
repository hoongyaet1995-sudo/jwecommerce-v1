import React, {useEffect, useState} from 'react';
import {createRoot} from 'react-dom/client';
import {ConnectStoreModal} from './components/marketplace/ConnectStoreModal';
import {marketplaceOperations, MarketplacePlatform, OAuthResult, requestMarketplaceAuthorization} from '../../integration/marketplaceOperations';
import './source.css';

function OAuthPreview() {
  const [availability, setAvailability] = useState<Partial<Record<MarketplacePlatform, boolean>>>({});
  const [result, setResult] = useState<OAuthResult | null>(null);
  useEffect(() => {
    let active = true;
    marketplaceOperations.connections().then(({capabilities}) => {
      if (active) setAvailability(Object.fromEntries(Object.entries(capabilities).map(([platform, capability]) => [platform, capability.oauth === true])));
    }).catch(() => {if (active) setAvailability({});});
    marketplaceOperations.oauthResult().then(({result}) => {if (active) setResult(result);})
      .catch(() => {if (active) setResult(null);});
    return () => {active = false;};
  }, []);
  return <ConnectStoreModal isOpen onClose={() => window.location.assign('/admin/marketplace/auth')}
    requestAuthorization={requestMarketplaceAuthorization} oauthAvailability={availability} authorizationResult={result}/>;
}

// Local isolated connection surface; capabilities fail closed while loading or on failure.
createRoot(document.getElementById('root')!).render(<OAuthPreview/>);
