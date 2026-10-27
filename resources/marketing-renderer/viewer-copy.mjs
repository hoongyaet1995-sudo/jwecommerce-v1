// All dynamic marketing text comes from the storyboard's visible-text field.
// No legacy presentation text or director-note fallback is permitted.
export function viewerPages(text,limit=48){
 if(typeof text!=='string'||!Number.isInteger(limit)||limit<1)throw Error('invalid_viewer_text');
 const chars=Array.from(text),pages=[];
 while(chars.length){
  let cut=Math.min(limit,chars.length);
  if(cut<chars.length){for(let i=cut-1;i>0;i--)if(/\s/u.test(chars[i])){cut=i+1;break;}}
  pages.push(chars.splice(0,cut).join(''));
 }
 return pages.length?pages:[''];
}
export function audiencePresentation(scene,frame){
 const copy=scene.viewer_copy;
 if(!copy||typeof copy.text!=='string'||copy.text!==scene.subtitle||copy.source!==`document.scenes.${scene.number-1}.subtitle`||!Number.isFinite(scene.frames)||scene.frames<1)throw Error('invalid_viewer_copy');
 const pages=viewerPages(copy.text),page=pages[Math.min(pages.length-1,Math.max(0,Math.floor(frame*pages.length/scene.frames)))];
 if(pages.length>scene.frames)throw Error('insufficient_viewer_copy_duration');
 const parts=viewerPages(page,24),pad=a=>[...a,'','',''].slice(0,3);
 if(scene.number===1)return {lines:pad(parts.slice(0,3)),heading:parts.slice(3).join(''),body:''};
 if(scene.number===2)return {lines:pad([parts[0]]),heading:parts.slice(1).join(''),body:''};
 if([3,4,5].includes(scene.number))return {heading:page,body:'',lines:pad([])};
 if(scene.number===6)return {heading:page,body:'',lines:pad([])};
 return {heading:parts[0],lines:pad(parts.slice(1)),body:''};
}
