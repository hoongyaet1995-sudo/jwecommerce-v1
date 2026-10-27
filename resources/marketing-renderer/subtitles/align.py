"""Align confirmed text to local audio via Whisper attention/DTW; no transcription substitution."""
import argparse,json,pathlib,wave,warnings
import numpy as np
import torch,whisper
from whisper.timing import find_alignment
from whisper.tokenizer import get_tokenizer

def main():
 p=argparse.ArgumentParser()
 for key in ['input','output','model-dir','script','language']:p.add_argument('--'+key,required=True)
 a=p.parse_args();path=pathlib.Path(a.model_dir)/'small.pt'
 if not path.is_file():raise ValueError('cached_alignment_model_missing')
 with wave.open(a.input,'rb') as f:
  if (f.getframerate(),f.getnchannels(),f.getsampwidth())!=(16000,1,2):raise ValueError('audio_format')
  audio=np.frombuffer(f.readframes(f.getnframes()),dtype=np.int16).astype(np.float32)/32768
 if not 0<len(audio)<=16000*16:raise ValueError('audio_length')
 torch.set_num_threads(4);model=whisper.load_model(str(path),device='cpu')
 tokenizer=get_tokenizer(model.is_multilingual,language=a.language,task='transcribe')
 tokens=tokenizer.encode(a.script)
 mel=whisper.log_mel_spectrogram(whisper.pad_or_trim(audio),n_mels=model.dims.n_mels)
 with torch.no_grad(),warnings.catch_warnings():
  warnings.simplefilter('ignore');aligned=find_alignment(model,tokenizer,tokens,mel,int(np.ceil(len(audio)/160)))
 words=[];limit=int(round(len(audio)/16000*24))
 for w in aligned:
  start=max(0,int(round(w.start*24)));end=min(limit,int(round(w.end*24)))
  if end<=start:
   if w.word.strip() and all(not c.isalnum() for c in w.word) and words:words[-1]['text']+=w.word
   elif w.word.strip():raise ValueError('spoken_word_without_reliable_timing')
   continue
  words.append({'text':w.word,'start_frame':start,'end_frame':end,'probability':round(float(w.probability),4)})
 normalize=lambda s:''.join(c for c in s if c.isalnum()).lower()
 if normalize(''.join(w['text'] for w in words))!=normalize(a.script) or not words:raise ValueError('alignment_text_mismatch')
 pathlib.Path(a.output).write_text(json.dumps({'version':'forced-script-dtw-v1','words':words,'script':a.script,'language':a.language,'audio_seconds':len(audio)/16000},ensure_ascii=False),encoding='utf-8')
if __name__=='__main__':main()
