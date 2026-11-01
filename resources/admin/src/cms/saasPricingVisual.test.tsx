import React from 'react';
import {it,expect} from 'vitest';
import {renderToStaticMarkup} from 'react-dom/server';
import {mkdirSync,readFileSync,writeFileSync} from 'node:fs';
import {saasSections} from './saasTemplates';
import {SaasPackages} from '../components/pages/SaasPackages';
import {SaasModules} from '../components/pages/SaasSections';
import {sectionIcons} from '../components/pages/IconPicker';
it('keeps pricing and add-on Inspector overrides and canonical icons while exporting local visual review',()=>{
 const sections=saasSections('pricing-review');const plans=sections.find(s=>s.type==='pricing_table')!,addons=sections.find(s=>s.featureCardsPreset==='saas-addons')!;
 const custom={...addons,typography:{itemTitle:{fontFamily:'mono' as const,fontSize:23,color:'#ddbb88'}}};
 const override=renderToStaticMarkup(<SaasModules section={custom} editing/>);
 expect(override).toContain('font-family:monospace');expect(override).toContain('color:#ddbb88');expect(override).toContain('23px');
 expect(sectionIcons.Store).toBeDefined();expect(sectionIcons.BarChart3).toBeDefined();
 const css=readFileSync('src/components/pages/SaasSections.css','utf8');
 const shell=(content:string)=>`<!doctype html><html lang="en"><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>JW Studio · Packages calibration</title><style>*{box-sizing:border-box}body{margin:0;background:#08111c;color:#f7f4ee;font-family:Arial,Helvetica,sans-serif}header{padding:20px 5%;background:#f7f4ee;color:#10202a;font-size:12px;display:flex;justify-content:space-between}section{container-type:inline-size}.frame{max-width:1280px;padding-inline:64px;margin:auto}a{color:inherit}svg{display:block}${css}@media(max-width:640px){.frame{padding-inline:24px}}.jw-saas-plan p,.jw-saas-addons .jw-saas-module p{color:#c6c5c0}</style><header><strong>JW STUDIO</strong><span>Packages &amp; add-ons · Design review</span></header><main>${content}</main></html>`;
 const html=renderToStaticMarkup(<><section><div className="frame"><SaasPackages section={plans} currency="MYR" editing/></div></section><section><div className="frame"><SaasModules section={addons} editing/></div></section></>);
 mkdirSync('../../public/saas-pricing-calibration',{recursive:true});writeFileSync('../../public/saas-pricing-calibration/index.html',shell(html));
});
