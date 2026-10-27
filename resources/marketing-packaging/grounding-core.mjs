import {framingAt} from './framing-core.mjs';
const valid=b=>Array.isArray(b)&&b.length===4&&b.every(Number.isFinite)&&b[0]>=-1e-8&&b[1]>=-1e-8&&b[2]>0&&b[3]>0&&b[0]+b[2]<=1+1e-8&&b[1]+b[3]<=1+1e-8;
const intersects=(a,b)=>a[0]<b[0]+b[2]&&a[0]+a[2]>b[0]&&a[1]<b[1]+b[3]&&a[1]+a[3]>b[1];
const transformBox=(b,t)=>[b[0]*t.scale+t.x,b[1]*t.scale+t.y,b[2]*t.scale,b[3]*t.scale];
export function manualSemanticEdit(value,patch){const out={...value,...patch};if(Object.keys(patch).some(k=>['title','body','scene_number','type','contact','channel'].includes(k))){delete out.semantic;out.source_refs=['user'];out.source='用户采用的文案';}if(value.grounding&&Object.keys(patch).some(k=>value[k]!==patch[k]))delete out.grounding;return out;}
export function layoutAvoidance(cards=[],overlays=[],scene){const out=[{start_frame:0,end_frame:2880,bbox:[0,.70,1,.30]}];for(const c of cards.filter(c=>c.scene_number===scene)){const height=56+Math.max(1,Math.ceil([...c.title||''].length/9))*32*1.45+7+Math.ceil([...c.body||''].length/13)*24*1.45+(c.type==='step'?44:0)+(c.channel!=='none'&&c.contact?12+Math.ceil([...c.contact].length/12)*25*1.45:0);out.push({start_frame:c.start_frame,end_frame:c.end_frame,bbox:[c.position==='top-right'?.36:c.position==='top-center'?.25:.075,66/1280,406/720,Math.min(.45,height/1280)]});}for(const o of overlays.filter(o=>o.scene_number===scene&&o.enabled!==false)){const w=o.width/100,h=w*720/(o.ratio||1.5)/1280;out.push({start_frame:o.start_frame,end_frame:o.end_frame,bbox:[o.x/100-w/2,o.y/100-h/2,w,h]});}return out;}
export function emphasisBounds(effect,x,y){const size=effect.size/100,stroke=.006;if(effect.kind!=='arrow')return [x-size/2-stroke,y-size/2-stroke,size+stroke*2,size+stroke*2];const angle=(effect.angle||0)*Math.PI/180,dx=Math.cos(angle),dy=Math.sin(angle),px=-dy,py=dx;const points=[[x-dx*size,y-dy*size],[x,y],[x+dx*.012,y+dy*.012],[x-dx*.048+px*.03,y-dy*.048+py*.03],[x-dx*.048-px*.03,y-dy*.048-py*.03]];const xs=points.map(p=>p[0]),ys=points.map(p=>p[1]);return [Math.min(...xs)-stroke,Math.min(...ys)-stroke,Math.max(...xs)-Math.min(...xs)+2*stroke,Math.max(...ys)-Math.min(...ys)+2*stroke];}
export function groundedEffectAt(effect,scene,framing,frame,currentAvoidance=null){
 if(!effect||frame<effect.start_frame||frame>=effect.end_frame)return null;
 const g=effect.grounding;if(!g)return effect;
 if(!scene||scene.number!==g.scene_number||scene.raw_sha256!==g.source_sha256||!g.intervals?.some(([a,b])=>frame>=a&&frame<b))return null;
 const points=g.points;if(!points?.length||frame<points[0].frame||frame>points.at(-1).frame)return null;
 let left=points[0],right=points.at(-1);for(let i=1;i<points.length;i++)if(frame<=points[i].frame){left=points[i-1];right=points[i];break;}
 if(!valid(left.bbox)||!valid(right.bbox)||(g.mode==='moving'&&right.frame-left.frame>12))return null;
 const amount=right.frame===left.frame?0:(frame-left.frame)/(right.frame-left.frame),raw=left.bbox.map((v,i)=>v+(right.bbox[i]-v)*amount),t=framingAt(framing,frame,scene.frames),box=transformBox(raw,t);
 if(!valid(box))return null;
 const obstacles=[...(currentAvoidance??g.avoidance??[]).filter(a=>frame>=a.start_frame&&frame<a.end_frame).map(a=>a.bbox),...[...(left.people||[]),...(right.people||[])].map(b=>transformBox(b,t))];
 const size=effect.size/100,radius=size/2;
 for(const [px,py] of [[.5,.5],[.75,.5],[.75,.3],[.75,.7],[.3,.5],[.5,.3],[.5,.7]]){
  const x=box[0]+box[2]*px,y=box[1]+box[3]*py;if(x<.05||x>.95||y<.05||y>.95)continue;
  const marker=emphasisBounds(effect,x,y);
  if(!valid(marker)||obstacles.some(b=>intersects(marker,b)))continue;
  if(effect.kind!=='arrow'&&(marker[0]<box[0]||marker[1]<box[1]||marker[0]+marker[2]>box[0]+box[2]||marker[1]+marker[3]>box[1]+box[3]))continue;
  return {...effect,x:x*100,y:y*100,targetBox:box};
 }
 return null;
}
export function supportedIntervals(effect,scene,framing){const ranges=[];let start=null;for(let frame=effect.start_frame;frame<=effect.end_frame;frame++){const visible=frame<effect.end_frame&&groundedEffectAt(effect,scene,framing,frame);if(visible&&start===null)start=frame;if(!visible&&start!==null){if(frame-start>=36)ranges.push([start,frame]);start=null;}}return ranges;}
