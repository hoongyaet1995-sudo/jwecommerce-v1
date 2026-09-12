import React from 'react';
import {createRoot} from 'react-dom/client';
import './source.css';
import {InventoryRuntime} from './InventoryRuntime';
import {RoutingPreview} from '../marketplace34/RoutingPreview';
import {WarehousePreview} from '../marketplace34/WarehousePreview';
import {TransferPreview} from '../marketplace34/TransferPreview';

const view=new URLSearchParams(location.search).get('view');
createRoot(document.getElementById('root')!).render(view==='routing'?<RoutingPreview/>:view==='warehouses'?<WarehousePreview/>:view==='transfers'?<TransferPreview/>:<InventoryRuntime zip36={view==='zip36'}/>);
