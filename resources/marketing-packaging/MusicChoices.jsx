import React from 'react';
import {uploadMusic} from './upload-music.mjs';

const I=({name})=><i className={`bi bi-${name}`} aria-hidden="true"/>;

export function MusicChoices({tracks,selected,onSelect,audition,onListen,view,onView,mood,onMood}){
 const moods=['全部风格',...new Set(tracks.filter(t=>t.kind==='library').flatMap(t=>t.tags||[]))];
 const visible=tracks.filter(t=>view==='mine'?t.kind==='upload':view==='recommended'?t.kind==='library'&&t.recommended:t.kind!=='upload').filter(t=>view==='mine'||mood==='全部风格'||t.tags?.includes(mood));
 const current=tracks.find(t=>t.id===selected);
 return <>
  <div className="music-browser-controls">
   <div className="preview-shortcuts" role="group" aria-label="音乐列表">
    {[['recommended','推荐音乐'],['all','全部音乐'],['mine','我的上传']].map(([key,label])=><button key={key} type="button" className={`none-button ${view===key?'selected':''}`} aria-pressed={view===key} onClick={()=>onView(key)}>{label}</button>)}
   </div>
   {view!=='mine'&&<label className="field music-mood">风格<select aria-label="筛选音乐风格" value={mood} onChange={e=>onMood(e.target.value)}>{moods.map(m=><option key={m}>{m}</option>)}</select></label>}
  </div>
  <p className="music-current">当前使用：{current?current.name:'不使用音乐'}{current?.kind==='internal'?' · 内部验收':''}</p>
  <div className="track-list" role="radiogroup" aria-label="背景音乐选择">{visible.map(t=><div key={t.id} className={`track-row ${selected===t.id?'selected':''}`}>
   <button className="track-play" aria-label={`${audition===t.id?'暂停':'试听'}${t.name}`} onClick={()=>onListen(t.id)}><I name={audition===t.id?'pause-fill':'play-fill'}/></button>
   <button className="track-select" role="radio" aria-checked={selected===t.id} onClick={()=>onSelect(t.id)}><span><b>{t.name}</b>{t.recommended&&<em>推荐</em>}</span><small>{t.mood}{t.author?' · '+t.author:''}</small></button>
   <div className="track-meta"><small>{Math.round(t.duration)}s · {t.kind==='library'?'免费':t.kind==='internal'?'内部验收':'我的上传'}</small><I name={selected===t.id?'check-circle-fill':'circle'}/></div>
  </div>)}</div>
  {!visible.length&&<p className="demo-disclosure">{view==='mine'?'还没有上传音乐，在下面选一个音频文件即可添加。':'此风格暂无曲目，可以切换全部风格或上传自己的音乐。'}</p>}
  {current?.kind==='library'&&<details className="source-details"><summary>当前音乐的来源与许可</summary><p>{current.author} · {current.license} · <a href={current.source_url} target="_blank" rel="noreferrer">原作者发布页</a> · <a href={current.license_url} target="_blank" rel="noreferrer">许可说明</a></p></details>}
 </>;
}

export function AudioUpload({runtime,onSaved}){
 const [busy,setBusy]=React.useState(false),[error,setError]=React.useState('');
 const submit=async e=>{e.preventDefault();const form=e.currentTarget;setBusy(true);setError('');try{const track=await uploadMusic(new FormData(form),runtime);runtime.addTrack(track);onSaved(track.id);form.reset();}catch(err){setError(err.message);}finally{setBusy(false);}};
 return <details className="source-details"><summary>上传我的音乐</summary><form onSubmit={submit}>
  <label className="field">音频文件 · MP3 / WAV / M4A / OGG<input type="file" name="file" accept=".mp3,.wav,.m4a,.ogg,audio/mpeg,audio/wav,audio/mp4,audio/ogg" required/></label>
  <p className="music-current">上传后即可试听和使用，仅本项目可见。请使用你有权用于本视频的音乐。</p>
  <label className="field">音乐名称 · 选填<input name="name" maxLength="100" placeholder="默认使用文件名"/></label>
  <button className="outline-button" disabled={busy} type="submit">{busy?'正在准备音乐…':'上传并选用'}</button>{error&&<p className="error-text" role="alert">{error}</p>}
 </form></details>;
}
