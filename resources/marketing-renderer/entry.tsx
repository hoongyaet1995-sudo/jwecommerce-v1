import React from 'react';
import {AbsoluteFill,Audio,Composition,Img,Sequence,interpolate,registerRoot,staticFile,useCurrentFrame} from 'remotion';
import {chunks} from './timeline.mjs';
import {HybridFilm} from './hybrid';
import {DynamicFilm} from './scene-composition';

const Scene=({scene,title,cta,revision,qa}:any)=>{
 const f=useCurrentFrame();const zoom=interpolate(f,[0,Math.max(1,scene.frames-1)],[1,1.025]);
 const pages=chunks(scene.subtitle||'',78);const page=pages[Math.min(pages.length-1,Math.floor(f*pages.length/scene.frames))];
 const images=scene.images||[];const image=images[Math.min(images.length-1,Math.floor(f*images.length/scene.frames))];
 const spoken=scene.captions.find((c:any)=>f/24>=c.start&&f/24<c.end)?.text||'';
 return <AbsoluteFill style={{background:'#f2f4f3',color:'#13251e',fontFamily:'Arial, sans-serif'}}>
  <div style={{position:'absolute',top:48,left:48,right:48,fontSize:22,color:'#52635a'}}>{qa?'LOCAL QA · ':''}Storyboard {revision} · Scene {scene.number}</div>
  <div style={{position:'absolute',top:104,left:48,right:48,fontWeight:700,fontSize:title.length>70?28:36,lineHeight:1.2,overflowWrap:'anywhere'}}>{title}</div>
  <div style={{position:'absolute',top:226,left:48,right:48,height:500,borderRadius:24,overflow:'hidden',background:'#fff',padding:20,display:'flex',alignItems:'center',justifyContent:'center'}}>
   {image?<Img src={staticFile(image.file)} style={{width:'100%',height:'100%',objectFit:'contain',transform:`scale(${zoom})`}}/>:<div style={{fontSize:40,fontWeight:700,padding:24,textAlign:'center'}}> {page} </div>}
  </div>
  <div style={{position:'absolute',top:778,left:48,right:48,minHeight:150,fontSize:36,fontWeight:700,lineHeight:1.28,overflowWrap:'anywhere'}}>{page}</div>
  <div style={{position:'absolute',top:1000,left:48,right:48,fontSize:27,lineHeight:1.3,color:'#52635a',overflowWrap:'anywhere'}}>{spoken}</div>
  <div style={{position:'absolute',bottom:58,left:48,right:48,borderTop:'2px solid #b3c5bb',paddingTop:22,fontSize:30,fontWeight:700}}>{cta}</div>
  {scene.audio?<Audio src={staticFile(scene.audio.file)}/>:null}
 </AbsoluteFill>;
};
const Film=(props:any)=>props.recipe==='capability-plan-v1'?<DynamicFilm {...props}/>:props.recipe==='use-scenario-hybrid-v1'?<HybridFilm {...props}/>:<AbsoluteFill>{props.scenes.map((s:any,i:number)=><Sequence key={i} from={s.start} durationInFrames={s.frames}><Scene scene={s} {...props}/></Sequence>)}</AbsoluteFill>;
const Root=()=><Composition id="MarketingVideo" component={Film} width={720} height={1280} fps={24} durationInFrames={24} defaultProps={{scenes:[]}} calculateMetadata={({props})=>({durationInFrames:props.scenes.reduce((n:number,s:any)=>n+s.frames,0)})}/>;
registerRoot(Root);
