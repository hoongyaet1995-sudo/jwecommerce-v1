"""Owned local music, aligned captions, audio mixing and verified mux. No network or SDK."""
from pathlib import Path
import json, sys, subprocess, hashlib, wave, math
import numpy as np
from captions import segments, ass_time

def main():
    allowed, root = (Path(x).resolve(strict=True) for x in sys.argv[1:])
    if allowed not in root.parents: raise ValueError('job_root_invalid')
    c=json.loads((root/'request.json').read_text(encoding='utf-8')); ff=c['ffmpeg']; probe=c['ffprobe']
    def run(args):
        p=subprocess.run(args,capture_output=True,text=True)
        if p.returncode: raise ValueError('local_media_failed')
        return p.stdout
    def meta(p):return json.loads(run([probe,'-v','error','-count_frames','-show_streams','-show_format','-of','json',str(p)]))
    def seconds(p):return float(meta(p)['format']['duration'])
    def fresh(p):
        if p.exists():raise ValueError('preserve_existing_output')
    def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
    voice=c.get('voice'); measured=0; start=c['planned_start']; music=None; audio=None
    if voice:
        vp=Path(voice['file']).resolve(strict=True)
        if allowed not in vp.parents or sha(vp)!=voice['sha256']:raise ValueError('voice_file_invalid')
        measured=seconds(vp)
        if not math.isfinite(measured) or measured<=0 or start<0 or start+measured>14.7:raise ValueError('voice_overrun')
    if c['music']:
        music=root/'original-soft-key.wav';fresh(music)
        sr=48000;t=np.arange(sr*15)/sr;signal=np.zeros(len(t));energy=float(c['energy']);beat=60/round(85+40*energy)
        if not 0<=energy<=1:raise ValueError('music_parameters_invalid')
        chords=[[48,55,59,64],[45,52,55,60],[41,48,52,57],[43,50,55,59]]
        for i,onset in enumerate(np.arange(0,15,beat)):
            note=chords[min(3,int(onset/3.75))][i%4]+12;x=t-onset;mask=(x>=0)&(x<2.5);y=x[mask];f=440*2**((note-69)/12)
            signal[mask]+=(.035+.035*energy)*(1-np.exp(-30*y))*np.exp(-2.3*y)*(np.sin(2*np.pi*f*y)+.18*np.sin(4*np.pi*f*y))
        signal*=np.minimum(1,t/.5)*np.minimum(1,np.maximum(0,(15-t)/1.2));stereo=np.clip(np.column_stack([signal,.94*signal]),-.9,.9)
        with wave.open(str(music),'wb') as w:w.setnchannels(2);w.setsampwidth(2);w.setframerate(sr);w.writeframes((stereo*32767).astype('<i2').tobytes())
    if voice or music:
        audio=root/'mix.wav';fresh(audio);inputs=[]
        if voice:
            inputs=['-i',str(vp)]; filters=f'[0:a]loudnorm=I=-18:TP=-2:LRA=7,adelay={round(start*1000)}:all=1,apad,atrim=duration=15[v]'
            if music:
                inputs+=['-i',str(music)];filters+=';[v]asplit=2[spoken][side];[1:a]volume=.45[m];[m][side]sidechaincompress=threshold=.015:ratio=4:attack=20:release=250[duck];[spoken][duck]amix=inputs=2:normalize=0,loudnorm=I=-16:TP=-1.5:LRA=9,alimiter=limit=.95:level=0,apad,atrim=duration=15[a]'
            else:filters+=';[v]anull[a]'
        else:inputs=['-i',str(music)];filters='[0:a]volume=.45,apad,atrim=duration=15[a]'
        prepad=root/'mix-prepad.wav';fresh(prepad)
        run([ff,'-v','error',*inputs,'-filter_complex',filters,'-map','[a]','-ar','48000','-ac','2','-n',str(prepad)])
        # Loudnorm/resampling may lose its tail padding; separate fixed-rate pad preserves speech.
        run([ff,'-v','error','-i',str(prepad),'-af','apad,atrim=duration=15','-ar','48000','-ac','2','-n',str(audio)])
    def captions(fmt):
        alignment=voice.get('alignment') if voice else None
        if not alignment:raise ValueError('caption_alignment_required')
        chars=alignment['characters'];starts=alignment['character_start_times_seconds'];ends=alignment['character_end_times_seconds']
        if not chars or len(chars)!=len(starts) or len(chars)!=len(ends):raise ValueError('caption_alignment_invalid')
        if ''.join(chars)!=c['narration_text']:raise ValueError('caption_alignment_invalid')
        vertical=fmt=='portrait';w,h=(1080,1920) if vertical else (1920,1080)
        # Reserved clear strip: between image and CTA portrait, below copy and above CTA landscape.
        header=f'[Script Info]\nScriptType: v4.00+\nPlayResX: {w}\nPlayResY: {h}\nWrapStyle: 2\n[V4+ Styles]\nFormat: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding\nStyle: Default,Microsoft YaHei,{30 if vertical else 28},&H00FFFFFF,&H00FFFFFF,&H00253D32,&H90253D32,0,0,0,0,100,100,0,0,3,6,0,2,{76 if vertical else 112},{76 if vertical else 1110},{260 if vertical else 450},1\n[Events]\nFormat: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text\n'
        rows=[]
        for cue in segments(chars,starts,ends,22 if vertical else 24):
            if cue['end']>measured+.1:raise ValueError('caption_alignment_invalid')
            safe=cue['text'].replace('\\','').replace('{','').replace('}','').replace('\r',' ').replace('\n',' ').strip()
            if safe: rows.append(f"Dialogue: 0,{ass_time(cue['start']+start)},{ass_time(cue['end']+start)},Default,,0,0,0,,{safe}")
        target=root/(fmt+'.ass');fresh(target);target.write_text(header+'\n'.join(rows),encoding='utf-8')
        return target
    outputs={}
    for fmt in c['formats']:
        source=root/(fmt+'-silent.mp4'); target=root/(fmt+'.mp4');fresh(target)
        args=[ff,'-v','error','-i',str(source)]
        if audio:args+=['-i',str(audio)]
        args+=['-map','0:v:0']
        if audio:args+=['-map','1:a:0','-c:a','aac','-b:a','192k']
        if c['subtitles']:
            ass=captions(fmt); escaped=str(ass).replace('\\','/').replace(':','\\:').replace("'","\\'")
            args+=['-vf',f"ass=filename='{escaped}'",'-c:v','libx264','-crf','18','-pix_fmt','yuv420p']
        else:args+=['-c:v','copy']
        run(args+['-movflags','+faststart','-n',str(target)])
        m=meta(target);vs=[s for s in m['streams'] if s['codec_type']=='video'];ats=[s for s in m['streams'] if s['codec_type']=='audio'];w,h=(1920,1080) if fmt=='landscape' else (1080,1920)
        if len(vs)!=1 or len(ats)!=(1 if audio else 0):raise ValueError('template_output_invalid')
        v=vs[0]
        if v['width']!=w or v['height']!=h or v['codec_name']!='h264' or int(v['nb_read_frames'])!=360 or v['r_frame_rate']!='24/1' or abs(float(v['duration'])-15)>.045 or abs(float(m['format']['duration'])-15)>.15:raise ValueError('template_output_invalid')
        if ats and (ats[0]['codec_name']!='aac' or abs(float(ats[0]['duration'])-15)>.15):raise ValueError('template_output_invalid')
        run([ff,'-v','error','-i',str(target),'-f','null','-'])
        outputs[fmt]={'filename':target.name,'sha256':sha(target),'width':w,'height':h,'seconds':15,'frames':360,'fps':24,'audio':bool(audio),'decode_checked':True,'probes':{str(f):sha(root/f'{fmt}-slot-{f}.png') for f in [24,96,168,216,264,324]}}
    receipt={'formats':outputs,'voice':{'measured_seconds':measured,'planned_start':start,'voice_speed':1,'speech_trimmed':False},'source_unchanged':True,'adapter':'product_template_v1','provider_calls':0}
    target=root/'receipt.json';fresh(target);target.write_text(json.dumps(receipt,ensure_ascii=False),encoding='utf-8');print(json.dumps({'status':'ok'}))
if __name__=='__main__':
    try:main()
    except Exception as e:
        code=str(e) if isinstance(e,ValueError) else 'local_media_failed'
        print(json.dumps({'error':code if code in ['voice_overrun','caption_alignment_required','caption_alignment_invalid','preserve_existing_output','job_root_invalid','voice_file_invalid','music_parameters_invalid','template_output_invalid'] else 'local_media_failed'}));sys.exit(1)
