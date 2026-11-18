import React from 'react';
import {createPortal} from 'react-dom';
import {AlignLeft,AlignCenter,AlignRight,RotateCcw,Eraser,Info,X} from 'lucide-react';
import './FloatingTextToolbar.css';
import type {PageSection} from '../../types';
import type {TextRole,TextStyle} from '../../cms/inspectorCapabilities';
import {fontCatalog} from '../../cms/fontCatalog';
import {FontPicker} from './FontPicker';
import {setTitleRange} from '../../cms/titleEmphasisRanges';
import {roleScope,editTextStyle,type HeadingSelection} from '../../cms/textToolbar';
export function FloatingTextToolbar({section,role,position,computed,selection,onChange,onClose}:{section:PageSection;role:TextRole;position:{left:number;top:number;width:number};computed:{fontSize:number;fontWeight:number;color:string};selection?:HeadingSelection;onChange:(s:PageSection)=>void;onClose:()=>void}){
 const style=section.typography?.[role]??{};const weight=style.fontWeight??computed.fontWeight;
 const previous=React.useRef<Record<string,number|undefined>>({});
 const update=(patch:Partial<TextStyle>)=>onChange(editTextStyle(section,role,patch));
 const weights=fontCatalog.find(f=>f.id===style.fontFamily)?.weights??[400,500,600,700,800,900];
 const bold=()=>{if(weight>=700){update({fontWeight:Object.prototype.hasOwnProperty.call(previous.current,role)?previous.current[role]:400});}else{previous.current[role]=style.fontWeight;update({fontWeight:700});}};
 const validSelection=selection&&section.title.slice(selection.start,selection.end)===selection.text?selection:undefined;
 const emphasis=(add:boolean)=>{if(validSelection)onChange({...section,titleEmphasis:setTitleRange(section.titleEmphasis??[],selection.start,selection.end,add,section.title.length)})};
 return createPortal(<div role="toolbar" aria-label="Text formatting toolbar" className="text-bubble cms-floating-text-toolbar dark" style={{left:position.left,top:position.top,width:position.width}} onKeyDown={e=>{if(e.key==='Escape')onClose()}}>
  <div className="bubble-controls" onPointerDown={e=>{if((e.target as HTMLElement).closest('button')&&!(e.target as HTMLElement).closest('[role="dialog"]'))e.preventDefault()}}>
   <span className="bubble-scope-icon" tabIndex={0} role="note" aria-label={'Applies to: '+roleScope(role)+'. Alignment applies to the section.'} title={'Typography: '+roleScope(role)+'. Alignment: this section.'}><Info size={15}/></span>
   <div className="bubble-font"><FontPicker value={style.fontFamily} onChange={fontFamily=>update({fontFamily,...(fontCatalog.find(f=>f.id===fontFamily)?.weights.includes(weight)?{}:{fontWeight:400})})}/></div>
   <input title="Font size" aria-label="Toolbar font size" type="number" min="8" max="120" value={style.fontSize??Math.round(computed.fontSize)} onChange={e=>{const n=Number(e.target.value);if(n>=8&&n<=120)update({fontSize:n})}}/>
   <select title="Font weight" aria-label="Toolbar font weight" value={weights.includes(weight)?weight:''} onChange={e=>update({fontWeight:Number(e.target.value)})}>{!weights.includes(weight)&&<option value="">{weight}</option>}{weights.map(w=><option key={w} value={w}>{w}</option>)}</select>
   <button title="Bold · applies to the whole text role" aria-label="Bold" aria-pressed={weight>=700} className={weight>=700?'active':''} onClick={bold}><b>B</b></button>
   <input title="Text color · applies to the whole text role" aria-label="Toolbar text color" type="color" value={style.color??computed.color} onChange={e=>update({color:e.target.value})}/>
   <span className="bubble-divider"/>
   {(['left','center','right'] as const).map((align,i)=>{const Icon=[AlignLeft,AlignCenter,AlignRight][i];return <button key={align} title={'Align '+align+' · this section'} aria-label={'Toolbar alignment '+align} aria-pressed={section.textAlignment===align} className={section.textAlignment===align?'active':''} onClick={()=>onChange({...section,textAlignment:align})}><Icon size={15}/></button>})}
   <button title="Use layout alignment" aria-label="Use layout alignment" onClick={()=>{const s={...section};delete s.textAlignment;onChange(s)}}><RotateCcw size={14}/></button>
   {role==='title'&&<><span className="bubble-divider"/><button title={selection?'Emphasize only “'+selection.text+'”':'Select part of this heading, then right-click'} aria-label="Emphasize selection" disabled={!validSelection} onClick={()=>emphasis(true)}>Emphasis</button><button title="Clear emphasis in the selected text" aria-label="Clear selected emphasis" disabled={!validSelection} onClick={()=>emphasis(false)}><Eraser size={15}/></button></>}
   <span className="bubble-divider"/>
   <button title="Reset text role style" aria-label="Reset text style" onClick={()=>{let s=section;for(const key of ['fontFamily','fontSize','fontWeight','color'] as const)s=editTextStyle(s,role,{[key]:undefined});onChange(s)}}>Reset</button>
   <button title="Close toolbar" aria-label="Close text toolbar" onClick={onClose}><X size={14}/></button>
  </div>
 </div>,document.body);
}
