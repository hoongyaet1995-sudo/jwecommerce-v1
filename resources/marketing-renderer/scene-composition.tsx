import React from 'react';
import {AbsoluteFill,Audio,Img,OffthreadVideo,Sequence,interpolate,staticFile,useCurrentFrame} from 'remotion';
import {viewerPages} from './viewer-copy.mjs';
import {presentationStyle,activeCaption} from './presentation.mjs';

// Explicit optional treatment. No inherited header/footer and no director-note text.
const ImmersiveScene=({scene,brand,cta,last}:any)=>{
 const f=useCurrentFrame();const spoken=activeCaption(scene,f);const image=scene.images[0];const video=scene.videos[0];
 const closing=last&&f>=Math.max(0,scene.frames-72);
 const pages=viewerPages(scene.subtitle,78);const copy=pages[Math.min(pages.length-1,Math.floor(f*pages.length/scene.frames))];
 return <AbsoluteFill style={{background:'#102735',color:'white',fontFamily:'Arial, sans-serif',overflow:'hidden'}}>
  {video?<OffthreadVideo src={staticFile(video.file)} muted style={{position:'absolute',inset:0,width:'100%',height:'100%',objectFit:'cover'}}/>:image?<Img src={staticFile(image.file)} style={{position:'absolute',inset:0,width:'100%',height:'100%',objectFit:'cover',transform:`scale(${interpolate(f,[0,Math.max(1,scene.frames-1)],[1,1.035])})`}}/>:<AbsoluteFill style={{justifyContent:'center',padding:64,boxSizing:'border-box',fontSize:48,fontWeight:800,lineHeight:1.2,overflowWrap:'anywhere',whiteSpace:'pre-wrap'}}>{copy}</AbsoluteFill>}
  {(spoken||closing)?<div style={{position:'absolute',inset:0,background:'linear-gradient(transparent 50%,rgba(0,0,0,.72))'}}/>:null}
  {spoken?<div style={{position:'absolute',left:55,right:70,bottom:closing?275:195,fontSize:38,fontWeight:700,lineHeight:1.22,textAlign:'center',textShadow:'0 2px 5px #000',overflowWrap:'anywhere'}}>{spoken}</div>:null}
  {closing?<div style={{position:'absolute',left:55,right:70,bottom:150,fontSize:32,fontWeight:800,textAlign:'center',textShadow:'0 2px 5px #000'}}><div style={{fontSize:24,marginBottom:10}}>{brand.name}</div>{cta}</div>:null}
  {scene.audio?<Audio src={staticFile(scene.audio.file)}/>:null}
 </AbsoluteFill>;
};

const DynamicScene=({scene,brand,cta,last}:any)=>{
 const f=useCurrentFrame();const pages=viewerPages(scene.subtitle,92);const copy=pages[Math.min(pages.length-1,Math.floor(f*pages.length/scene.frames))];
 const spoken=scene.captions.find((c:any)=>f/24>=c.start&&f/24<c.end)?.text||'';const image=scene.images[0];const video=scene.videos[0];
 return <AbsoluteFill style={{background:'#f1f3f2',color:'#102735',fontFamily:'Arial, sans-serif',overflow:'hidden'}}>
  <div style={{position:'absolute',top:48,left:52,right:52,borderBottom:'3px solid #d88646',paddingBottom:22}}><div style={{fontSize:35,fontWeight:800}}>{brand.name}</div><div style={{fontSize:17,letterSpacing:2,marginTop:6}}>{brand.descriptor}</div></div>
  <div style={{position:'absolute',top:190,left:40,right:40,height:625,overflow:'hidden',borderRadius:16,background:'#fff',display:'flex',alignItems:'center',justifyContent:'center'}}>
   {video?<OffthreadVideo src={staticFile(video.file)} muted style={{width:'100%',height:'100%',objectFit:'cover'}}/>:image?<Img src={staticFile(image.file)} style={{width:'100%',height:'100%',objectFit:'contain',transform:`scale(${interpolate(f,[0,Math.max(1,scene.frames-1)],[1.01,1.06])})`}}/>:<div style={{padding:45,fontSize:48,fontWeight:800,lineHeight:1.2,textAlign:'center',overflowWrap:'anywhere',whiteSpace:'pre-wrap'}}>{copy}</div>}
  </div>
  {(image||video)?<div style={{position:'absolute',top:850,left:52,right:52,fontSize:38,fontWeight:800,lineHeight:1.2,overflowWrap:'anywhere',whiteSpace:'pre-wrap'}}>{copy}</div>:null}
  <div style={{position:'absolute',top:1055,left:52,right:52,fontSize:25,lineHeight:1.35,color:'#526773',overflowWrap:'anywhere'}}>{spoken}</div>
  {last?<div style={{position:'absolute',bottom:45,left:52,right:52,borderTop:'3px solid #d88646',paddingTop:18,fontSize:30,fontWeight:800}}>{cta}</div>:null}
  {scene.audio?<Audio src={staticFile(scene.audio.file)}/>:null}
 </AbsoluteFill>;
};
export const DynamicFilm=(props:any)=>{const Scene=presentationStyle(props.presentation)==='immersive-v1'?ImmersiveScene:DynamicScene;return <AbsoluteFill>{props.scenes.map((s:any,i:number)=><Sequence key={s.scene_id} from={s.start} durationInFrames={s.frames}><Scene scene={s} brand={props.presentation.brand} cta={props.cta} last={i===props.scenes.length-1}/></Sequence>)}</AbsoluteFill>;};
