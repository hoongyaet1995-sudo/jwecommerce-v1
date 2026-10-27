import React from 'react';
import {AbsoluteFill,Composition,OffthreadVideo,Sequence,interpolate,registerRoot,staticFile,useCurrentFrame} from 'remotion';
const width=(text:string)=>Array.from(text).reduce((n,c)=>n+(/[^\x00-\xff]/.test(c)?2:1),0);
const Shot=({scene,cta,hold=72}:{scene:any,cta:string,hold?:number})=>{
 const frame=useCurrentFrame();const lines=scene.subtitle?scene.subtitle.split('\n'):[];const size=Math.min(36,1216/Math.max(1,...lines.map(width)));const opacity=interpolate(frame,[0,6],[0,1],{extrapolateRight:'clamp'});const closing=cta&&!scene.subtitle.includes(cta)&&frame>=Math.max(0,scene.frames-hold);
 return <AbsoluteFill style={{background:'#10231c',fontFamily:'Microsoft YaHei, Arial, sans-serif'}}>
  <OffthreadVideo src={staticFile(scene.file)} muted={!scene.with_audio} {...(Number.isInteger(scene.in_frame)?{trimBefore:scene.in_frame,durationInFrames:scene.frames}:{})} style={{width:'100%',height:'100%',objectFit:'contain'}}/>
  {(lines.length>0||closing)?<AbsoluteFill style={{background:'linear-gradient(transparent 60%,rgba(0,0,0,.65) 100%)',pointerEvents:'none'}}/>:null}
  <div style={{position:'absolute',left:54,right:70,bottom:170,color:'#fff',textAlign:'center',textShadow:'0 2px 6px rgba(0,0,0,.95)',opacity,lineHeight:1.35}}>{lines.map((line:string,i:number)=><div key={i} style={{fontSize:i===0?Math.min(30,size):size,fontWeight:700,marginTop:i?8:0,whiteSpace:'pre-wrap',overflowWrap:'anywhere'}}>{line}</div>)}</div>
  {closing?<div style={{position:'absolute',left:54,right:70,bottom:78,color:'#fff',textAlign:'center',fontWeight:700,fontSize:Math.min(30,1216/Math.max(1,width(cta))),textShadow:'0 2px 6px #000'}}>{cta}</div>:null}
 </AbsoluteFill>;
};
const Film=({scenes,cta,cta_hold_frames=72}:any)=>{let start=0;return <AbsoluteFill>{scenes.map((scene:any,i:number)=>{const from=start;start+=scene.frames;return <Sequence key={i} from={from} durationInFrames={scene.frames}><Shot scene={scene} cta={i===scenes.length-1?cta:''} hold={cta_hold_frames}/></Sequence>;})}</AbsoluteFill>;};
registerRoot(()=> <Composition id="WorkspaceVideo" component={Film} width={720} height={1280} fps={24} durationInFrames={24} defaultProps={{scenes:[],cta:''}} calculateMetadata={({props}:any)=>({durationInFrames:props.scenes.reduce((sum:number,s:any)=>sum+s.frames,0)})}/>);
