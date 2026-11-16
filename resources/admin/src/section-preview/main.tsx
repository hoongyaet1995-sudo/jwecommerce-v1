import React,{useEffect,useRef} from 'react';
import {createRoot} from 'react-dom/client';
import {SECTION_TEMPLATES} from '../components/pages/AddSectionModal';
import {SectionRenderer} from '../components/pages/SectionRenderer';
import {PageRuntimeProvider} from '../cms/PageRuntimeContext';
import {createNewSection} from '../cms/sectionStarterDefaults';
import {createSharedSection,sharedSectionFamilies} from '../cms/sharedSectionLibrary';
import {sectionPreviewPayload} from '../cms/sectionPreviewRuntime';
import './style.css';
const query=new URLSearchParams(location.search);
const card=Number(query.get('card'));
const width=Number(query.get('width'))===390?390:1280;
const template=Number.isInteger(card)?SECTION_TEMPLATES[card]:undefined;
function report(error=false){parent.postMessage({type:'cms-section-preview',card,height:Math.max(1,Math.ceil(document.getElementById('root')!.getBoundingClientRect().height)),images:[...document.images].filter(i=>i.complete&&!i.naturalWidth).length,error},location.origin)}
class Boundary extends React.Component<{children:React.ReactNode},{error:boolean}>{state={error:false};static getDerivedStateFromError(){return {error:true}}componentDidCatch(){report(true)}render(){return this.state.error?<p role="alert">This preview could not be loaded.</p>:this.props.children}}
function Frame(){
 const section=useRef((()=>{if(!template)return null;const family=sharedSectionFamilies.find(f=>f.id===template.sharedFamily);const layout=query.get('layout');const data=family&&family.layouts.some(l=>l.id===layout)?createSharedSection(family.id,layout!):template.defaultData;return createNewSection(data,card)})()).current;
 useEffect(()=>{let disposed=false,ready=false;const send=()=>{if(ready&&!disposed)report()};const observer=new ResizeObserver(send);observer.observe(document.getElementById('root')!);Promise.all([document.fonts.ready,...[...document.images].map(i=>{i.loading='eager';return i.decode().catch(()=>undefined)})]).then(()=>{ready=true;requestAnimationFrame(send)});const blockNavigation=(e:MouseEvent)=>{if((e.target as Element).closest('a'))e.preventDefault()};document.addEventListener('click',blockNavigation);return()=>{disposed=true;observer.disconnect();document.removeEventListener('click',blockNavigation)}},[]);
 if(!section)return <p role="alert">Select a section to preview.</p>;
 return <Boundary><PageRuntimeProvider payload={sectionPreviewPayload(section,false)}><SectionRenderer section={section} isEditingMode viewport={width===390?'mobile':'desktop'}/></PageRuntimeProvider></Boundary>;
}
createRoot(document.getElementById('root')!).render(<Frame/>);
