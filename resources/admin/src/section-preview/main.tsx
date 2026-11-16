import React,{useEffect,useRef} from 'react';
import {createRoot} from 'react-dom/client';
import {SECTION_TEMPLATES} from '../components/pages/AddSectionModal';
import {SectionRenderer} from '../components/pages/SectionRenderer';
import {PageRuntimeProvider} from '../cms/PageRuntimeContext';
import {createNewSection} from '../cms/sectionStarterDefaults';
import {createSharedSection,sharedSectionFamilies} from '../cms/sharedSectionLibrary';
import {sectionPreviewPayload} from '../cms/sectionPreviewRuntime';
import {isPageStyle,type PageStyle} from '../cms/pageStyle';
import {auditSectionReadability} from '../cms/testing/sectionReadabilityAudit';
import './style.css';
const query=new URLSearchParams(location.search);
if(query.has('readability')){const stable=document.createElement('style');stable.textContent='.cms-connected-native *{transition:none!important;animation:none!important}';document.head.append(stable);}
const card=Number(query.get('card'));
const width=Number(query.get('width'))===390?390:1280;
const template=Number.isInteger(card)?SECTION_TEMPLATES[card]:undefined;
let pageStyle:PageStyle|undefined;
try{const value=JSON.parse(query.get('style')||'null');if(isPageStyle(value))pageStyle=value}catch{/* Invalid preview parameters never enter the page runtime. */}
function report(error=false){const root=document.getElementById('root')!;parent.postMessage({type:'cms-section-preview',card,job:Number(query.get('job')??card),layout:query.get('layout')??'',height:Math.max(1,Math.ceil(root.getBoundingClientRect().height)),images:[...document.images].filter(i=>i.complete&&!i.naturalWidth).length,error,...(query.has('readability')?{readability:auditSectionReadability(root)}:{})},location.origin)}
class Boundary extends React.Component<{children:React.ReactNode},{error:boolean}>{state={error:false};static getDerivedStateFromError(){return {error:true}}componentDidCatch(){report(true)}render(){return this.state.error?<p role="alert">This preview could not be loaded.</p>:this.props.children}}
function Frame(){
 const section=useRef((()=>{if(!template)return null;const family=sharedSectionFamilies.find(f=>f.id===template.sharedFamily);const layout=query.get('layout');const data=family&&family.layouts.some(l=>l.id===layout)?createSharedSection(family.id,layout!):template.defaultData;return {...createNewSection(data,card),...(query.has('readability')?{motionPreset:'none' as const}:{}),surface:query.get('surface')==='contrast'?'contrast' as const:'light' as const}})()).current;
 useEffect(()=>{let disposed=false,ready=false;const send=()=>{if(ready&&!disposed)report()};const observer=new ResizeObserver(send);observer.observe(document.getElementById('root')!);Promise.all([document.fonts.ready,...[...document.images].map(i=>{i.loading='eager';return i.decode().catch(()=>undefined)})]).then(()=>{if(query.has('readability'))setTimeout(()=>{ready=true;send()},350);else{ready=true;requestAnimationFrame(send)}});const blockNavigation=(e:MouseEvent)=>{if((e.target as Element).closest('a'))e.preventDefault()};document.addEventListener('click',blockNavigation);return()=>{disposed=true;observer.disconnect();document.removeEventListener('click',blockNavigation)}},[]);
 if(!section)return <p role="alert">Select a section to preview.</p>;
 return <Boundary><PageRuntimeProvider payload={sectionPreviewPayload(section,false,pageStyle)}><SectionRenderer section={section} isEditingMode viewport={width===390?'mobile':'desktop'}/></PageRuntimeProvider></Boundary>;
}
createRoot(document.getElementById('root')!).render(<Frame/>);

