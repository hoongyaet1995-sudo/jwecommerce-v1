export function presentationStyle(presentation){
 const style=presentation?.style??'legacy';
 if(!['legacy','immersive-v1'].includes(style))throw Error('unsupported_presentation');
 return style;
}
export function activeCaption(scene,frame){
 return (scene.captions||[]).find(c=>frame/24>=c.start&&frame/24<c.end)?.text||'';
}
