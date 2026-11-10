import React from 'react';
import {it,expect} from 'vitest';
import {readFileSync} from 'node:fs';
import {renderToStaticMarkup} from 'react-dom/server';
import {SaasPackages} from '../components/pages/SaasPackages';
import {SaasModules} from '../components/pages/SaasSections';
import type {PageSection} from '../types';
const source=JSON.parse(readFileSync('../../docs/planning/corporate-capabilities/pricing-source-2026-11-09.json','utf8')) as PageSection[];
it('renders original published prices, entitlements and addon copy with a bounded light theme',()=>{
 const original=JSON.stringify(source);const light=source.map(s=>({...s,textColor:'dark' as const,customColors:{...s.customColors,customBgColor:'#ffffff',titleColor:'#0d1234',contentColor:'#626981',primaryBtnBg:'#2860ff',eyebrowColor:'#6334ed',primaryBtnText:'#ffffff',secondaryBtnBg:'#ffffff',secondaryBtnText:'#0d1234'}}));
 const html=renderToStaticMarkup(<><SaasPackages section={light[0]} currency="MYR" editing/><SaasModules section={light[1]} editing/></>);
 expect(html).toContain('data-saas-tone="light"');expect(html).toContain('color:#0d1234');expect(html).toContain('linear-gradient');expect(html).toContain('299');expect(html).toContain('399');expect(html).toContain('+RM100 / store / month');expect(html).toContain('+RM200 / month');expect(html).toContain('Usage allowances and provider limits are confirmed before activation.');expect(html).toContain('Supported messaging channels');expect(source[0].items!.map(x=>x.price)).toEqual([299,399,undefined,undefined]);expect(JSON.stringify(source)).toBe(original);
});
it('preserves the existing dark pricing and addons appearance path',()=>{
 const html=renderToStaticMarkup(<><SaasPackages section={source[0]} currency="MYR" editing/><SaasModules section={source[1]} editing/></>);expect(html).not.toContain('data-saas-tone="light"');expect(html).not.toContain('linear-gradient');expect(html).toContain('#c7ab7b');expect(html).toContain('AI ENABLED');
});
