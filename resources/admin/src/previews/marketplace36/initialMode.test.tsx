import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {expect,it} from 'vitest';
import {ModeSwitchModal} from './components/marketplace/ModeSwitchModal';

it('initial setup does not offer to redistribute stock or invent an employee',()=>{
 const html=renderToStaticMarkup(<ModeSwitchModal isOpen currentMode="unconfigured" onClose={()=>{}}
   onConfirmSwitch={async()=>({success:true})} skuCount={1}/>);
 expect(html).not.toContain('Stock Partitioning Strategy');
 expect(html).not.toContain('Alexander (Super Admin)');
});
