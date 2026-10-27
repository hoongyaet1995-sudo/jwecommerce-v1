import fs from 'node:fs/promises';
import path from 'node:path';
import crypto from 'node:crypto';
import {createRequire} from 'node:module';
import {fileURLToPath} from 'node:url';
const own=path.dirname(fileURLToPath(import.meta.url)),deps=path.dirname(own),req=createRequire(path.join(deps,'package.json'));
const {bundle}=req('@remotion/bundler'),{selectComposition,renderStill,renderMedia,openBrowser}=req('@remotion/renderer');
const [allowed,rootArg]=process.argv.slice(2);
const root=await fs.realpath(rootArg),base=await fs.realpath(allowed);
if(!root.startsWith(base+path.sep))throw Error('job_root_invalid');
const config=JSON.parse(await fs.readFile(path.join(root,'request.json'),'utf8')),brief=config.brief;
// Composition defaults are never permitted to supply missing actual-job copy.
for(const k of ['source','brand','disclosure','language','product','name','category','hook','benefit','support','cta','accent','ink','bg'])if(typeof brief[k]!=='string'||(!['brand','disclosure','support'].includes(k)&&!brief[k].trim()))throw Error('brief_incomplete');
if(!['zh','en'].includes(brief.language)||!Array.isArray(brief.details)||brief.details.length!==3)throw Error('brief_invalid');
for(const d of brief.details)if(!d||['title','copy','source','position'].some(k=>typeof d[k]!=='string'||(k!=='copy'&&!d[k].trim()))||typeof d.scale!=='number'||d.scale<1||d.scale>2)throw Error('brief_invalid');
if(!Array.isArray(config.formats)||!config.formats.length||config.formats.some(f=>!['landscape','portrait'].includes(f))||new Set(config.formats).size!==config.formats.length)throw Error('formats_invalid');
for(const p of [brief.source,...brief.details.map(d=>d.source)])if(path.basename(p)!==p)throw Error('asset_path_invalid');
const sourceHash=crypto.createHash('sha256').update(await fs.readFile(path.join(own,'component.tsx'))).digest('hex');
if(sourceHash!=='b25dfc665301fbe9331b3bf7e8015caa86ced8ecfa9fc9b529d95f2be1b13cde')throw Error('template_source_changed');
// Validate every text region with Chromium's actual installed fonts, without changing source DOM.
const browser=await openBrowser('chrome',{browserExecutable:config.browser,logLevel:'warn'});
try {
 const page=await browser.newPage({context:()=>null,logLevel:'warn',indent:false,pageIndex:0,onBrowserLog:null,onLog:()=>{}});
 await page.evaluate((b,formats,subtitles)=>{
  function height(text,size,width,lineHeight,weight=650,spacing=1){const el=document.createElement('div');Object.assign(el.style,{position:'absolute',fontFamily:'Microsoft YaHei, Segoe UI, sans-serif',fontSize:size+'px',fontWeight:String(weight),width:width+'px',lineHeight:String(lineHeight),letterSpacing:spacing+'px',whiteSpace:'pre-line',overflowWrap:'normal'});el.textContent=text;document.body.appendChild(el);const h=el.getBoundingClientRect().height;el.remove();return h;}
  const ctx=document.createElement('canvas').getContext('2d');
  function width(text,size,spacing=0,weight=650){ctx.font=`${weight} ${size}px Microsoft YaHei, Segoe UI, sans-serif`;return ctx.measureText(text).width+text.length*spacing;}
  for(const fmt of formats){const vertical=fmt==='portrait',w=vertical?920:700;
   if(width(b.brand,24,4)> (vertical?928:650)||width(b.disclosure,19)> (vertical?928:650)||width(b.cta,vertical?35:34,0,600)+40+36+(vertical?88:76)> (vertical?928:700)||width(b.category,25,2)>(vertical?928:700))throw Error('text_does_not_fit_template');
   for(const [i,text,support] of [[0,b.hook,''],[1,b.benefit,b.support],...b.details.map((d,i)=>[2,d.title,d.copy]),[3,b.name,'']]){
    const base=b.language==='en'?54:(i===0?(vertical?83:88):(vertical?68:76));let size=base;
    while(size>=Math.ceil(base*.72)&&height(text,size,w,1.48)>300)size--;
    if(size<Math.ceil(base*.72))throw Error('text_does_not_fit_template');
    const available=vertical?340:((subtitles?610:675)-(i===0?330:310));
    if(height(text,size,w,1.48)+(support?22+height(support,vertical?29:30,vertical?900:650,1.9,400,0):0)>available)throw Error('text_does_not_fit_template');
   }
  }
 },brief,config.formats,config.subtitles);
 await page.close();
} finally {await browser.close({silent:true});}
if(process.argv[4]==='validate'){console.log(JSON.stringify({text_fit:true,source_hash:sourceHash}));process.exit(0);}
const serveUrl=await bundle({entryPoint:path.join(own,'component.tsx'),rootDir:deps,publicDir:path.join(root,'assets'),webpackOverride:c=>({...c,resolve:{...c.resolve,modules:[path.join(deps,'node_modules'),...(c.resolve?.modules||[])]}})});
for(const fmt of config.formats){
 const composition=await selectComposition({serveUrl,id:fmt==='landscape'?'Landscape':'Portrait',inputProps:brief,browserExecutable:config.browser});
 // Six exposed slots exercise original fitted() measurement before expensive video rendering.
 for(const frame of [24,96,168,216,264,324]){
  const output=path.join(root,`${fmt}-slot-${frame}.png`);
  await fs.access(output).then(()=>{throw Error('preserve_existing_output')},e=>{if(e.code!=='ENOENT')throw e});
  await renderStill({serveUrl,composition,inputProps:brief,browserExecutable:config.browser,frame,output,logLevel:'warn'});
 }
 const outputLocation=path.join(root,`${fmt}-silent.mp4`);
 await fs.access(outputLocation).then(()=>{throw Error('preserve_existing_output')},e=>{if(e.code!=='ENOENT')throw e});
 await renderMedia({serveUrl,composition,inputProps:brief,browserExecutable:config.browser,codec:'h264',pixelFormat:'yuv420p',outputLocation,concurrency:2,crf:18,logLevel:'warn'});
}
console.log(JSON.stringify({source_hash:sourceHash,provider_calls:0}));
