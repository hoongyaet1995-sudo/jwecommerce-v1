export function assertHybridInput(m){
 const layouts=['need_open','product_intro','use_map','business_uses','requirements','cta_close'];
 const credit=m.audio?.credit_authorization;
 const boundedCredit=credit?.max_requests===1&&Number.isInteger(credit.max_credits)&&credit.max_credits>0&&credit.max_credits<=100&&credit.existing_credits_only===true&&credit.topup_allowed===false&&credit.retry_allowed===false;
 if(m.recipe!=='use-scenario-hybrid-v1'||(m.audio?.reuse_only!==true&&!boundedCredit)||m.scenes?.length!==6||m.presentation?.scenes?.length!==6)throw Error('invalid_hybrid_recipe');
 const brand=m.presentation?.brand;
 if(m.copy_contract!=='storyboard-viewer-copy-v1')throw Error('missing_viewer_copy_contract');
 if(typeof brand?.name!=='string'||!brand.name||brand.name.length>16||typeof brand.descriptor!=='string'||brand.descriptor.length>60)throw Error('invalid_hybrid_brand');
 m.scenes.forEach((s,i)=>{
  if(typeof s.viewer_copy?.text!=='string'||s.viewer_copy.text!==s.subtitle||s.viewer_copy.source!==`document.scenes.${i}.subtitle`)throw Error('invalid_viewer_copy');
  if(s.number!==i+1||m.presentation.scenes[i]?.layout!==layouts[i])throw Error('invalid_hybrid_layout');
  if((s.videos||[]).length!==(i===0?1:0)||(s.images||[]).length!==([1,4].includes(i)?1:0))throw Error('missing_hybrid_material');
  for(const media of [...(s.images||[]),...(s.videos||[])])if(!/^[a-f0-9]{64}\.(?:mp4|png|jpg|webp)$/.test(media.file))throw Error('invalid_staged_media');
 });
}
