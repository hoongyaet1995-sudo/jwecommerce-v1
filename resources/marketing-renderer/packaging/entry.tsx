import React from 'react';
import {AbsoluteFill,Composition,Img,interpolate,registerRoot,staticFile,useCurrentFrame} from 'remotion';
const Card=({settings,brandFile,productFile}:any)=>{
 const frame=useCurrentFrame();const product=settings.template==='product';const cta=settings.template==='cta';const fade=interpolate(frame,[0,8],[0,1],{extrapolateRight:'clamp'});
 return <AbsoluteFill style={{background:cta?'#123b29':'#f8f8f1',fontFamily:'Arial, Microsoft YaHei, sans-serif',display:'flex',alignItems:'center',justifyContent:'center',padding:57}}>
  {product&&<Img src={staticFile(productFile)} style={{position:'absolute',inset:0,width:'100%',height:'100%',objectFit:'contain',objectPosition:'center 26%',background:'#edf0eb'}}/>}
  <div style={{opacity:fade,zIndex:1,display:'flex',flexDirection:'column',alignItems:'center',textAlign:'center',gap:product?36:64,width:'100%',...(product?{position:'absolute',left:57,right:57,bottom:77,width:'auto',padding:'57px 36px',background:'#f9fbf2ef',border:'3px solid #e1e7d5',borderRadius:21}:{})}}>
   <div style={{display:'flex',justifyContent:'center',alignItems:'center',padding:product?0:'32px 46px',maxWidth:'80%',background:product?'transparent':'#fafaf5',borderRadius:14}}><Img src={staticFile(brandFile)} style={{display:'block',width:product?356:534,maxWidth:'100%',height:'auto'}}/></div>
   <strong style={{fontSize:50,fontWeight:600,lineHeight:1.8,color:cta?'#f4f4df':'#183547',overflowWrap:'anywhere',maxWidth:'90%'}}>{settings.company}</strong>
   {settings.template!=='minimal'&&<span style={{fontSize:43,lineHeight:1.7,color:cta?'#c7df95':'#385e40',maxWidth:'95%',overflowWrap:'anywhere',...(cta?{borderTop:'3px solid #54723e',paddingTop:53}:{})}}>{settings.cta}</span>}
  </div>
 </AbsoluteFill>;
};
registerRoot(()=> <Composition id="BrandOutro" component={Card} width={720} height={1280} fps={24} durationInFrames={72} defaultProps={{settings:{duration:3},brandFile:'brand.svg',productFile:null}} calculateMetadata={({props}:any)=>({durationInFrames:props.settings.duration*24})}/>);
