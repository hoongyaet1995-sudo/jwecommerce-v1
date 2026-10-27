import React from 'react';
import {AbsoluteFill,Composition,Img,registerRoot,staticFile,useCurrentFrame,useVideoConfig,interpolate} from 'remotion';
type Detail={source?:string;title:string;copy:string;position:string;scale:number};
type Brief={source:string;brand:string;disclosure:string;language:string;product:string;name:string;category:string;hook:string;benefit:string;support:string;details:Detail[];cta:string;accent:string;ink:string;bg:string};
function fitted(text:string,base:number,width:number,maxHeight:number){
 if(typeof document==='undefined')return base;
 const ctx=document.createElement('canvas').getContext('2d');if(!ctx)throw Error('text_measurement_unavailable');
 for(let size=base;size>=Math.ceil(base*.72);size--){
  ctx.font=`650 ${size}px Microsoft YaHei, Segoe UI, sans-serif`;
  let lines=0;for(const paragraph of text.split('\n')){let line='';lines++;for(const ch of paragraph){const trial=line+ch;if(ctx.measureText(trial).width+trial.length>width){lines++;line=ch}else line=trial}}
  if(lines*size*1.48<=maxHeight)return size;
 }throw Error('text_does_not_fit_template');
}
const Film:React.FC<Brief>=(b)=>{
 const f=useCurrentFrame(),{width,height}=useVideoConfig(),vertical=height>width;
 const stage=f<48?0:f<144?1:f<288?2:3;
 const detailIndex=Math.min(2,Math.max(0,Math.floor((f-144)/48)));const d=b.details[detailIndex];
 const local=stage===0?f:stage===1?f-48:stage===2?(f-144)%48:f-288;
 const enter=interpolate(local,[0,10],[0,1],{extrapolateLeft:'clamp',extrapolateRight:'clamp'});
 const rise=interpolate(local,[0,12],[18,0],{extrapolateRight:'clamp'});
 const copy=stage===0?b.hook:stage===1?b.benefit:stage===2?d.title:b.name;
 const support=stage===1?b.support:stage===2?d.copy:'';
 const panel=vertical?{left:64,top:530,width:952,height:1110}:{left:880,top:70,width:940,height:940};
 const x=vertical?76:112;const textTop=vertical?170:stage===0?330:310; const font=fitted(copy,b.language==='en'?54:(stage===0?(vertical?83:88):(vertical?68:76)),vertical?920:700,vertical?300:300);
 return <AbsoluteFill style={{background:b.bg,color:b.ink,fontFamily:'Microsoft YaHei, Segoe UI, sans-serif'}}>
  <div style={{position:'absolute',left:x,top:vertical?68:70,fontFamily:'Segoe UI,sans-serif',fontSize:24,letterSpacing:4,fontWeight:650}}>{b.brand}</div>
  <div style={{position:'absolute',left:x,top:vertical?126:127,width:vertical?928:650,height:2,background:b.accent,opacity:.35}}/>
  <div style={{position:'absolute',left:x,top:textTop,width:vertical?920:700,opacity:enter,transform:`translateY(${rise}px)`}}>
   <div style={{fontSize:font,lineHeight:1.48,fontWeight:650,letterSpacing:1,whiteSpace:'pre-line'}}>{copy}</div>
   {support&&<div style={{fontSize:vertical?29:30,lineHeight:1.9,marginTop:22,color:b.ink,opacity:.78,maxWidth:vertical?900:650}}>{support}</div>}
  </div>
  <div style={{position:'absolute',...panel,overflow:'hidden',borderRadius:vertical?18:24,background:'#fbfaf7'}}>
   <Img src={staticFile(stage===2?(d.source||b.source):b.source)} style={{width:'100%',height:'100%',objectFit:stage===2?'cover':'contain',objectPosition:stage===2?d.position:'50% 50%',transform:stage===2?`scale(${d.scale})`:'none'}}/>
   {stage===2&&<div style={{position:'absolute',left:26,top:26,borderRadius:25,padding:'10px 20px',background:'rgba(255,255,255,.9)',color:b.ink,fontSize:22}}>细节 {detailIndex+1} / 3</div>}
  </div>
  {stage===3?<div style={{position:'absolute',left:x,top:vertical?1690:680,display:'inline-flex',alignItems:'center',gap:40,background:b.ink,color:'#fff',borderRadius:12,padding:vertical?'24px 44px':'24px 38px',fontSize:vertical?35:34,fontWeight:600,opacity:enter}}>{b.cta}<span style={{fontSize:36}}>→</span></div>:
  stage!==0&&<div style={{position:'absolute',left:x,top:vertical?1698:740,fontSize:25,letterSpacing:2,color:b.accent}}>{stage===1?b.category: `${String(detailIndex+1).padStart(2,'0')} / 看得见的细节`}</div>}
  <div style={{position:'absolute',left:x,bottom:vertical?82:67,fontSize:19,color:b.ink,opacity:.6}}>{b.disclosure}</div>
  <div style={{position:'absolute',left:x,right:vertical?76:1090,bottom:vertical?44:35,height:3,background:'#dcded6'}}><div style={{height:'100%',width:`${(f+1)/360*100}%`,background:b.accent}}/></div>
 </AbsoluteFill>;
};
const defaults:Brief={source:'front.png',brand:'JW STUDIO',disclosure:'AI概念营销样片 · 非在售商品',language:'zh',product:'fashion',name:'松弛感衬衫',category:'日常穿搭 · 概念款',hook:'穿得松弛一点。',benefit:'日常，也有自己的风格。',support:'从衣领、口袋和织纹，看看这件衬衫。',details:[{title:'利落衣领',copy:'先看一眼，领口的线条。',position:'50% 0%',scale:1.22},{title:'双口袋设计',copy:'让细节，成为穿搭的一部分。',position:'50% 37%',scale:1.25},{title:'看得见的织纹',copy:'靠近一点，看看表面层次。',position:'50% 58%',scale:1.45}],cta:'查看款式',accent:'#7a6650',ink:'#253d32',bg:'#f8f5ef'};
registerRoot(()=> <><Composition id="Landscape" component={Film} durationInFrames={360} fps={24} width={1920} height={1080} defaultProps={defaults}/><Composition id="Portrait" component={Film} durationInFrames={360} fps={24} width={1080} height={1920} defaultProps={defaults}/></>);
