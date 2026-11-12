import React from 'react';
import type {PageSection} from '../../types';
import {typographyStyle} from '../../cms/inspectorCapabilities';
import {ScopedImage} from './ScopedImage';
import './WideVisualEditorial.css';
/** Single-media narrative passport: no operational controls or repeatable cards. */
export function WideVisualEditorial({section:s,editing=false}:{section:PageSection;editing?:boolean}){
 if(['inset-surface-panel','split-visual-surface'].includes(s.layoutPreset??''))return <div className="cms-split-surface-wrap" style={{paddingBlock:s.paddingY==='compact'?'32px':s.paddingY==='normal'?'64px':'96px'}}><div className={`cms-split-surface ${s.layoutPreset==='inset-surface-panel'?'cms-inset-surface':''}`} data-wide-visual="surface-panel" style={{'--surface-paper':s.customColors?.customBgColor||'transparent','--surface-main':s.customColors?.primaryBtnBg||'currentColor','--surface-sub':s.customColors?.secondaryBtnBg||'currentColor'} as React.CSSProperties}>
  <div className="cms-split-surface-copy"><h2 data-cms-text="title" style={typographyStyle(s,'title')}>{s.title}</h2>{s.content&&<p data-cms-text="content" style={typographyStyle(s,'content')}>{s.content}</p>}</div>
  <div className="cms-split-surface-art">{s.mediaUrl?<ScopedImage section={s} targetId="mediaUrl" src={s.mediaUrl} alt={s.mediaAlt||s.title} loading="lazy" decoding="async"/>:editing?<p>Add an image in Content → Images.</p>:null}</div>
 </div></div>;
 const centered=s.layoutPreset==='wide-visual-centered';
 return <div className={`cms-wide-editorial ${centered?'cms-wide-centered':''}`} data-wide-visual={centered?'centered':'editorial'} style={{paddingBlock:s.paddingY==='compact'?'32px':s.paddingY==='normal'?'64px':'96px'}}>
  <header className="cms-wide-editorial-header"><div>{!centered&&s.subtitle&&<p className="cms-wide-editorial-eyebrow" data-cms-text="eyebrow" style={typographyStyle(s,'eyebrow')}>{s.subtitle}</p>}<h2 data-cms-text="title" style={typographyStyle(s,'title')}>{s.title}</h2></div>{s.content&&<p className="cms-wide-editorial-body" data-cms-text="content" style={typographyStyle(s,'content')}>{s.content}</p>}</header>
  <div className="cms-wide-editorial-media">{s.mediaUrl?<ScopedImage section={s} targetId="mediaUrl" src={s.mediaUrl} alt={s.mediaAlt||s.title} loading="lazy" decoding="async"/>:editing?<p>Add an image or GIF in Content → Images.</p>:null}</div>
 </div>;
}
