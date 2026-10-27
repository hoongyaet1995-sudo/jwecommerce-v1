"""Deterministic local PCM mixing; actual dialogue RMS drives ducking. No network/AI."""
import json,sys,wave
from pathlib import Path
import numpy as np

def read(path,frames):
    out=np.zeros((frames,2),dtype=np.float64)
    if not path:return out
    with wave.open(str(path),'rb') as f:
        if f.getnchannels()!=2 or f.getsampwidth()!=2 or f.getframerate()!=48000:raise ValueError('pcm_format')
        data=np.frombuffer(f.readframes(min(frames,f.getnframes())),dtype='<i2').reshape(-1,2).astype(np.float64)/32768
    out[:len(data)]=data
    return out

def mix(directory):
    directory=Path(directory);m=json.loads((directory/'mix-input.json').read_text(encoding='utf-8'))
    frames=m['frames'];content=m['content_frames'];volume=m['volume']
    if type(frames)!=int or type(content)!=int or not 1<=content<=frames<=2880 or type(volume)!=int or not 0<=volume<=100:raise ValueError('invalid_mix')
    n=frames*2000;voice=read(directory/'voice.wav' if m['voice'] else None,n)
    voice[content*2000:]=0
    music=read(directory/'music.wav' if m['music'] else None,n)
    gain=np.ones(n,dtype=np.float64);chunk=960;level=1.0;active_samples=0
    if m['duck'] and m['voice'] and m['music']:
        for start in range(0,n,chunk):
            end=min(n,start+chunk);rms=float(np.sqrt(np.mean(voice[start:end]**2)));active=rms>.02
            target=.25 if active else 1.0;tau=.020 if active else .300
            next_level=target+(level-target)*np.exp(-(end-start)/48000/tau)
            gain[start:end]=np.linspace(level,next_level,end-start);level=next_level
            if active:active_samples+=end-start
    if m['fade']:
        t=np.arange(n)/48000
        gain*=np.minimum(1,t/1.0)*np.clip((n/48000-t)/1.2,0,1)
    bed=music*(volume/100)*gain[:,None]
    out=voice+bed;peak=float(np.max(np.abs(out)));headroom=min(1,.95/max(peak,1e-9));out*=headroom
    with wave.open(str(directory/'mixed.wav'),'wb') as f:
        f.setnchannels(2);f.setsampwidth(2);f.setframerate(48000);f.writeframes(np.round(out*32767).astype('<i2').tobytes())
    result={'sample_rate':48000,'samples':n,'duration':n/48000,'duck_method':'actual_dialogue_rms_20ms_attack20ms_release300ms' if m['duck'] else 'none','speech_detected_seconds':active_samples/48000,'music_volume_percent':volume,'duck_floor_db':-12.0412,'fade_in_seconds':1 if m['fade'] else 0,'fade_out_seconds':1.2 if m['fade'] else 0,'headroom_gain':headroom,'output_peak':float(np.max(np.abs(out))),'voice_preserved_samples':content*2000}
    (directory/'audio-mix.json').write_text(json.dumps(result,indent=2),encoding='utf-8');return result

if __name__=='__main__':print(json.dumps(mix(sys.argv[1])))
