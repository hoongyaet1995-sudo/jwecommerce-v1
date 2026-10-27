// Isolated acceptance composition; not wired into the merchant production workflow.
import React from 'react';
import {AbsoluteFill,Composition,OffthreadVideo,interpolate,registerRoot,staticFile,useCurrentFrame} from 'remotion';
const Clip=({lines}: {lines:string[]})=>{
 const frame=useCurrentFrame();const opacity=interpolate(frame,[0,6],[0,1],{extrapolateRight:'clamp'});
 return <AbsoluteFill style={{background:'#10231c',fontFamily:'Microsoft YaHei, Arial, sans-serif'}}>
  <OffthreadVideo src={staticFile('raw.mp4')} style={{width:'100%',height:'100%',objectFit:'contain'}}/>
  <AbsoluteFill style={{background:'linear-gradient(transparent 60%,rgba(0,0,0,.65) 100%)',pointerEvents:'none'}}/>
  <div style={{position:'absolute',left:54,right:70,bottom:170,color:'#fff',textAlign:'center',textShadow:'0 2px 6px rgba(0,0,0,.95)',opacity,lineHeight:1.35}}>
   {lines.map((line,i)=><div key={i} style={{fontSize:i===0?30:36,fontWeight:700,marginTop:i?8:0}}>{line}</div>)}
  </div>
 </AbsoluteFill>;
};
registerRoot(()=> <Composition id="JtgOneShot" component={Clip} width={720} height={1280} fps={24} durationInFrames={120} defaultProps={{lines:[]}}/>);
