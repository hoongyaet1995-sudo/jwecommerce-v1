const clamp=(n,a,b)=>Math.max(a,Math.min(b,n));const ease=t=>t*t*(3-2*t);
export function framingAt(c,frame,frames){
 if(!c||c.mode==='none'||frame<c.start_frame||frame>=c.end_frame)return {scale:1,x:0,y:0};
 const duration=Math.max(1,c.end_frame-c.start_frame),t=clamp((frame-c.start_frame)/Math.max(1,duration-1),0,1),edge=Math.min(8,Math.floor(duration/4));
 const entrance=c.mode==='fixed'?ease(clamp((frame-c.start_frame)/edge,0,1)):ease(t);
 const exit=c.end_frame<frames?ease(clamp((c.end_frame-1-frame)/edge,0,1)):1;
 const scale=1+(clamp(c.zoom,1,1.3)-1)*entrance*exit;
 return {scale,x:clamp(.5-scale*clamp(c.x/100,0,1),1-scale,0),y:clamp(.5-scale*clamp(c.y/100,0,1),1-scale,0)};
}
