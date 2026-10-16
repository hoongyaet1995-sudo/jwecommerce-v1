import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { JSDOM } from 'jsdom';

const source=readFileSync(new URL('../../../public/js/cms-shell-design.js',import.meta.url),'utf8');
function fixture(){
 const dom=new JSDOM(`<header data-shell-header><div data-header-main></div><nav class="jw-shell-desktop"><details class="jw-shell-dropdown" open><summary>Containers</summary><div class="jw-shell-submenu"><a href="/collections/jtg-20ft-containers">20ft Containers</a></div></details><details class="jw-shell-dropdown"><summary>Other</summary><div class="jw-shell-submenu"><a href="/shop">Other link</a></div></details></nav></header><button id="outside">Outside</button>`,{url:'http://localhost/',runScripts:'outside-only'});
 dom.window.ResizeObserver=class{observe(){}};
 dom.window.eval(source);
 return dom;
}

test('summary blur to an inside anchor survives transient body activeElement',async()=>{
 const dom=fixture(),w=dom.window,d=w.document,menu=d.querySelector('details'),anchor=menu.querySelector('a');
 assert.equal(d.activeElement,d.body);
 menu.querySelector('summary').dispatchEvent(new w.FocusEvent('focusout',{bubbles:true,relatedTarget:anchor}));
 await Promise.resolve();
 assert.equal(menu.open,true,'The mousedown focus transition must not remove the anchor before click');
 dom.window.close();
});

test('keyboard focus outside closes the dropdown',async()=>{
 const dom=fixture(),w=dom.window,d=w.document,menu=d.querySelector('details');
 menu.querySelector('a').dispatchEvent(new w.FocusEvent('focusout',{bubbles:true,relatedTarget:d.querySelector('#outside')}));
 await Promise.resolve();assert.equal(menu.open,false);dom.window.close();
});

test('switching dropdowns closes only the previous dropdown',()=>{
 const dom=fixture(),w=dom.window,menus=w.document.querySelectorAll('details');
 menus[1].open=true;menus[1].dispatchEvent(new w.Event('toggle'));
 assert.equal(menus[0].open,false);assert.equal(menus[1].open,true);dom.window.close();
});

test('outside click and Escape continue to close menus',()=>{
 const dom=fixture(),w=dom.window,d=w.document,menu=d.querySelector('details');
 d.querySelector('#outside').click();assert.equal(menu.open,false);
 menu.open=true;d.dispatchEvent(new w.KeyboardEvent('keydown',{key:'Escape',bubbles:true}));assert.equal(menu.open,false);dom.window.close();
});
