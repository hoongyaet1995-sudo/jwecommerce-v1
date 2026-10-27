import React from 'react';
import {AbsoluteFill,Audio,Composition,registerRoot,staticFile,useCurrentFrame} from 'remotion';
const Fixture=()=>{const f=useCurrentFrame();return <AbsoluteFill style={{background:`rgb(${f*2},${255-f*2},40)`,color:'white',fontSize:80}}><Audio src={staticFile('tone.wav')}/><div style={{margin:50}}>source frame {f}</div></AbsoluteFill>;};
registerRoot(()=> <Composition id="Fixture" component={Fixture} width={720} height={1280} fps={24} durationInFrames={120}/>);
