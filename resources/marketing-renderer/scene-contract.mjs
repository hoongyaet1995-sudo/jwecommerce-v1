import {presentationStyle} from './presentation.mjs';
export function assertSceneInput(m){
 presentationStyle(m.presentation);
 const hybrid=m.execution_profile==='single-shot-hybrid-v1';
 if(m.recipe!=='capability-plan-v1'||(!hybrid&&m.execution_profile!=='local-only-v1')||m.ratio!=='9:16'||m.audio?.reuse_only!==true||m.audio?.new_requests!==0)throw Error('invalid_dynamic_profile');
 if(hybrid){
  const r=m.generation_receipts;
  if(!Number.isInteger(m.budget?.cap_microusd)||m.budget.cap_microusd<1000000||m.budget.cap_microusd>1100000||m.budget.ai_video_requests!==1||!Array.isArray(r)||r.length!==1||r[0].status!=='completed'||!/^generate-scene-[1-6]$/.test(r[0].node_key)||!/^[a-f0-9]{64}$/.test(r[0].sha256))throw Error('invalid_generation_receipt');
  const index=Number(r[0].node_key.slice(-1))-1;if(m.scenes?.[index]?.videos?.[0]?.file!==r[0].sha256+'.mp4')throw Error('unbound_generation_receipt');
 }else if(m.budget?.cap_microusd!==0||m.budget?.ai_video_requests!==0||m.generation_receipts!==undefined)throw Error('invalid_dynamic_budget');
 const brand=m.presentation?.brand;if(typeof brand?.name!=='string'||!brand.name.trim()||brand.name.length>16||typeof brand.descriptor!=='string'||brand.descriptor.length>60)throw Error('invalid_dynamic_brand');
 if(!Array.isArray(m.scenes)||m.scenes.length<3||m.scenes.length>6)throw Error('invalid_dynamic_scenes');
 let duration=0;
 m.scenes.forEach((s,i)=>{
  const fields=['number','scene_id','mode','images','videos','voiceover','subtitle','planned_seconds','minimum_frames','viewer_copy'];
  if(Object.keys(s).some(k=>!fields.includes(k))||s.number!==i+1||s.scene_id!==`scene-${i+1}`||!Number.isInteger(s.planned_seconds)||s.planned_seconds<1||s.planned_seconds>60)throw Error('invalid_dynamic_scene');
  duration+=s.planned_seconds;
  if(typeof s.subtitle!=='string'||s.viewer_copy?.text!==s.subtitle||s.viewer_copy?.source!==`document.scenes.${i}.subtitle`||typeof s.voiceover!=='string')throw Error('invalid_dynamic_copy');
  if(!Array.isArray(s.images)||!Array.isArray(s.videos))throw Error('invalid_dynamic_media');
  const expected={existing_images:[1,0],existing_video:[0,1],text_card:[0,0]}[s.mode];
  if(!expected||s.images.length!==expected[0]||s.videos.length!==expected[1])throw Error('missing_dynamic_media');
  for(const media of [...s.images,...s.videos])if(!/^[a-f0-9]{64}\.(?:mp4|png|jpg|webp)$/.test(media.file)||!Number.isInteger(media.width)||!Number.isInteger(media.height)||media.width<1||media.height<1)throw Error('invalid_staged_media');
  if(s.videos.length&&(s.videos[0].has_audio!==false||!Number.isFinite(s.videos[0].duration_seconds)||s.videos[0].duration_seconds+.1<s.planned_seconds))throw Error('unsupported_source_audio_or_duration');
 });
 if(duration>60)throw Error('dynamic_duration_exceeds_profile');
}
