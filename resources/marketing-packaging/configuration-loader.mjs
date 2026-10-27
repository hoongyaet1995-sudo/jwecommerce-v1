// Per-mounted-film cache. The adopt endpoint still performs fresh server validation.
export function configurationLoader(url,send=fetch,now=Date.now){
 let cached=null,loadedAt=0,pending=null;
 return {
  async get(){
   if(cached&&now()-loadedAt<30000)return structuredClone(cached);
   if(!pending)pending=(async()=>{
    const response=await send(url,{headers:{Accept:'application/json'}});
    const data=await response.json();
    if(!response.ok)throw Error(data.message||'当前成片或品牌资料尚未准备好');
    cached=data;loadedAt=now();return data;
   })().finally(()=>{pending=null;});
   return structuredClone(await pending);
  },
  updateSubtitles(state){if(cached)cached.subtitles=structuredClone(state);},
  addImage(image){if(cached)cached.overlay_images=[image,...(cached.overlay_images||[]).filter(i=>i.id!==image.id)];},
  addTrack(track){if(cached)cached.tracks=[track,...cached.tracks.filter(t=>t.id!==track.id)];}
 };
}
