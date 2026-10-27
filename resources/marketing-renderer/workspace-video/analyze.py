"""Local cached ASR, timing evidence only. No expected-script prompt or model download."""
import argparse, difflib, json, pathlib, wave
import numpy as np
import whisper

def main():
    parser=argparse.ArgumentParser()
    parser.add_argument('--input',required=True)
    parser.add_argument('--output',required=True)
    parser.add_argument('--model-dir',required=True)
    parser.add_argument('--script',default='')
    args=parser.parse_args()
    model=pathlib.Path(args.model_dir)/'small.pt'
    if not model.is_file(): raise RuntimeError('cached_model_missing')
    with wave.open(args.input,'rb') as wav:
        if wav.getframerate()!=16000 or wav.getnchannels()!=1 or wav.getsampwidth()!=2: raise ValueError('audio_format')
        audio=np.frombuffer(wav.readframes(wav.getnframes()),dtype=np.int16).astype(np.float32)/32768.0
    result=whisper.load_model(str(model),device='cpu').transcribe(audio,fp16=False,word_timestamps=True,verbose=False,temperature=0,condition_on_previous_text=False)
    words=[{'start':float(w['start']),'end':float(w['end']),'text':w['word'],'probability':float(w.get('probability',0))} for seg in result.get('segments',[]) for w in seg.get('words',[])]
    normalized=lambda text: ''.join(c.lower() for c in text if c.isalnum())
    expected,heard=normalized(args.script),normalized(result.get('text',''))
    coverage=difflib.SequenceMatcher(None,expected,heard,autojunk=False).ratio() if expected else 1.0
    output={'method':'whisper-small-local-20250625','transcript':result.get('text',''),'words':words[:512], 'script_similarity':coverage,'language':result.get('language'),'timing_only':True}
    pathlib.Path(args.output).write_text(json.dumps(output,ensure_ascii=False),encoding='utf-8')

if __name__=='__main__': main()
