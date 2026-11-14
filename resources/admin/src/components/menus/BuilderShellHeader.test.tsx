// @vitest-environment jsdom
import React,{act} from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {createRoot} from 'react-dom/client';
import {it,expect,vi} from 'vitest';
vi.mock('../../../../../public/css/cms-shell-design.css?url',()=>({default:'/build-admin/assets/cms-shell-design-test.css'}));
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

it('inherits shop logo even with display brand name',()=>{
 const html=renderToStaticMarkup(<BuilderShellHeader value={{...shellDesignDefaults('service'),style:'modern',brandName:'JW Studio',brandLogo:''}} brand="PRO Computer Centre" logo="/pro-logo.png" menus={[]} mobile={false} presentation={{mode:'normal',tone:'dark'}}/>);
 expect(html).toContain('JW Studio');expect(html).not.toContain('PRO Computer');expect(html).toContain('/pro-logo.png');expect(html).toContain('data-shell-style="unified"');
});

it('resolves desktop and mobile links using current draft ownership without binding corporate IDs',async()=>{
 const pages=[{id:'home',slug:'home',status:'draft',sections:[{id:'plans',isVisible:true}]},{id:'feature',slug:'feature',status:'draft',sections:[{id:'hero',isVisible:true}]}] as any;const menus=[{id:'pricing',title:'Pricing',url:'#section-plans',isActive:true,sortOrder:0,columns:[]},{id:'platform',title:'Platform',url:'#section-plans',isActive:true,sortOrder:1,columns:[{id:'c',title:'Feature',url:'/pages/feature',isActive:true,items:[]}]}] as any;
 const node=document.createElement('div');document.body.append(node);const root=createRoot(node);await act(async()=>root.render(<BuilderShellHeader value={shellDesignDefaults('service')} brand="Studio" menus={menus} currentPage={pages[1]} pages={pages} mobile={false} presentation={{mode:'normal',tone:'dark'}}/>));expect(node.querySelector('.jw-shell-desktop a')?.getAttribute('href')).toBe('/admin/pages/home/preview#section-plans');
 await act(async()=>root.render(<BuilderShellHeader value={shellDesignDefaults('service')} brand="Studio" menus={menus} currentPage={pages[1]} pages={pages} mobile presentation={{mode:'normal',tone:'dark'}}/>));await act(async()=>node.querySelector<HTMLButtonElement>('[aria-label="Open navigation"]')!.click());expect(node.querySelector('.jw-shell-mobile a')?.getAttribute('href')).toBe('/admin/pages/home/preview#section-plans');expect([...node.querySelectorAll('.jw-shell-mobile a')].some(a=>a.getAttribute('href')==='/admin/pages/feature/preview')).toBe(true);await act(async()=>root.unmount());node.remove();
});

it('uses measured canvas width for a working mobile menu and restores wide desktop mode',async()=>{
 let notify:ResizeObserverCallback=()=>{};let observed:Element|undefined;const disconnect=vi.fn();
 vi.stubGlobal('ResizeObserver',class{constructor(callback:ResizeObserverCallback){notify=callback}observe(element:Element){observed=element}disconnect(){disconnect()}});
 const node=document.createElement('div');document.body.append(node);const root=createRoot(node);
 const menus=[{id:'m',title:'Platform',url:'/platform',isActive:true,sortOrder:0,columns:[{id:'c',title:'Commerce',url:'/commerce',isActive:true,items:[]}]}] as any;
 await act(async()=>root.render(<BuilderShellHeader value={shellDesignDefaults('service')} brand="Studio" menus={menus} mobile={false} presentation={{mode:'transparent',tone:'light'}}/>));
 const resize=async(width:number)=>act(async()=>notify([{target:observed,contentRect:{width}}] as ResizeObserverEntry[],{} as ResizeObserver));
 await resize(532);expect(node.querySelector('header')?.dataset.mobile).toBe('true');expect((node.querySelector('header') as HTMLElement).style.marginBottom).toBe('0px');
 await act(async()=>node.querySelector<HTMLButtonElement>('[aria-label="Open navigation"]')!.click());expect(node.querySelector('[aria-label="Mobile navigation"]')?.textContent).toContain('Commerce');
 await resize(1200);expect(node.querySelector('header')?.dataset.mobile).toBe('false');expect(node.querySelector('[aria-label="Mobile navigation"]')).toBeNull();
 await resize(788);expect(node.querySelector('[aria-label="Mobile navigation"]')?.textContent).toContain('Commerce');
 await act(async()=>root.unmount());expect(disconnect).toHaveBeenCalledOnce();node.remove();vi.unstubAllGlobals();
});
