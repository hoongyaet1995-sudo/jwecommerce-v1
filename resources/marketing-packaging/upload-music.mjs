export async function uploadMusic(body,runtime,send=fetch){
 body.set('_token',runtime.csrf);
 const response=await send(runtime.upload_url,{method:'POST',body,headers:{Accept:'application/json'}});
 const data=await response.json();
 if(!response.ok)throw Error(Object.values(data.errors||{}).flat()[0]||data.message||'音乐上传未完成');
 if(!data.track?.id||!data.track?.url||data.track.kind!=='upload')throw Error('音乐已保存，但未取得试听资料；请重新打开音乐列表。');
 return data.track;
}
