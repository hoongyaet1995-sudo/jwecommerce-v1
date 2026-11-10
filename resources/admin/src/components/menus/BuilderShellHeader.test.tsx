// @vitest-environment jsdom
import React,{act} from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {createRoot} from 'react-dom/client';
import {it,expect} from 'vitest';
import {BuilderShellHeader} from './BuilderShellHeader';
import {shellDesignDefaults} from '../../cms/shellDesign';
it('renders company logo and actual grouped menus, applies mobile and overlay settings',async()=>{
 const node=document.createElement('div');document.body.append(node);const root=createRoot(node);
 const menus=[{id:'root',title:'Stories',url:'/stories',isActive:true,sortOrder:0,columns:[{id:'group',title:'City',url:'/city',isActive:true,items:[{id:'leaf',title:'Wedding',url:'/wedding',isActive:true}]}]}] as any;
 await act(async()=>root.render(<BuilderShellHeader value={shellDesignDefaults('service')} brand="Studio" logo="/company.png" menus={menus} mobile={false} presentation={{mode:'transparent',tone:'light'}}/>));
 expect(node.querySelector('img')?.getAttribute('src')).toBe('/company.png');expect(node.querySelector('.jw-shell-submenu-grouped')?.textContent).toContain('Wedding');expect((node.querySelector('header') as HTMLElement).style.marginBottom).toBe('-88px');
 await act(async()=>root.render(<BuilderShellHeader value={shellDesignDefaults('service')} brand="Studio" menus={menus} mobile presentation={{mode:'transparent',tone:'light'}}/>));
 await act(async()=>node.querySelector<HTMLButtonElement>('[aria-label="Open navigation"]')!.click());expect(node.querySelector('[aria-label="Mobile navigation"]')?.textContent).toContain('Wedding');expect((node.querySelector('header') as HTMLElement).style.marginBottom).toBe('0px');await act(async()=>root.unmount());node.remove();
});

it('uses optional display branding without leaking the commerce brand or logo',()=>{
 const html=renderToStaticMarkup(<BuilderShellHeader value={{...shellDesignDefaults('service'),style:'modern',brandName:'JW Studio',brandLogo:''}} brand="PRO Computer Centre" logo="/pro-logo.png" menus={[]} mobile={false} presentation={{mode:'normal',tone:'dark'}}/>);
 expect(html).toContain('JW Studio');expect(html).not.toContain('PRO Computer');expect(html).not.toContain('/pro-logo.png');
});
