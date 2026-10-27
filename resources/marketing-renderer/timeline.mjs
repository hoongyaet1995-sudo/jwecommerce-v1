export function chunks(text, limit=78) {
 const result=[]; let line='';
 for(const word of (text.match(/\S+/gu)||[])) {
  if(line && line.length+word.length+1>limit){result.push(line);line='';}
  line+=(line?' ':'')+word;
 }
 if(line)result.push(line);
 return result.length?result:[''];
}

export function buildTimeline(source, alignment, audioDuration=0) {
 const voiced=source.map((s,i)=>({text:s.voiceover||'',i})).filter(s=>s.text.trim());
 const text=voiced.map(s=>s.text).join('\n\n');
 if(text && (!alignment || alignment.characters.join('')!==text || alignment.characters.length!==alignment.character_start_times_seconds.length || alignment.characters.length!==alignment.character_end_times_seconds.length))throw Error('voice_alignment_mismatch');
 const offsets=[];let offset=0;
 for(const v of voiced){offsets.push(offset);offset+=[...v.text].length+2;}
 const cuts=text?[0,...offsets.slice(1).map(o=>(alignment.character_end_times_seconds[o-3]+alignment.character_start_times_seconds[o])/2),audioDuration]:[];
 let start=0;
 const scenes=source.map((s,i)=>{
  const at=voiced.findIndex(v=>v.i===i);const cutStart=at>=0?cuts[at]:0;const cutEnd=at>=0?cuts[at+1]:0;
  if(s.minimum_frames!==undefined&&(!Number.isInteger(s.minimum_frames)||s.minimum_frames<1||s.minimum_frames>2880))throw Error('invalid_scene_timing');
  const seconds=cutEnd-cutStart;const frames=Math.ceil(Math.max(s.planned_seconds,seconds+(at>=0?.6:0),(s.minimum_frames||0)/24)*24);
  if(!Number.isFinite(frames)||frames<1)throw Error('invalid_scene_timing');
  const captions=[];
  if(at>=0){
   const words=[...s.voiceover.matchAll(/\S+/gu)];
   for(let n=0;n<words.length;n+=7){
    const part=words.slice(n,n+7);const a=part[0].index;const b=part.at(-1).index+part.at(-1)[0].length;
    const lo=offsets[at]+[...s.voiceover.slice(0,a)].length;const hi=offsets[at]+[...s.voiceover.slice(0,b)].length-1;
    captions.push({text:s.voiceover.slice(a,b),start:Math.max(0,alignment.character_start_times_seconds[lo]-cutStart),end:alignment.character_end_times_seconds[hi]-cutStart});
   }
  }
  const scene={...s,start,frames,captions,audio:at>=0?{start:cutStart,end:cutEnd,file:`voice-${i}.wav`}:null};start+=frames;return scene;
 });
 if(start>120*24)throw Error('duration_exceeds_profile');
 return {scenes,frames:start};
}
