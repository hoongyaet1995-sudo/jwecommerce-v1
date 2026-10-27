"""Pure aligned subtitle segmentation; preserves words and source time offsets."""
import math
import re

TOKEN = re.compile(r'\s+|[\u3400-\u9fff\u3000-\u303f\uff00-\uffef]|[^\s\u3400-\u9fff\u3000-\u303f\uff00-\uffef]+')

def ass_time(seconds):
    ticks = round(seconds*100)
    return f'{ticks//360000}:{ticks//6000%60:02}:{ticks//100%60:02}.{ticks%100:02}'

def segments(chars, starts, ends, limit):
    if not chars or len(chars)!=len(starts) or len(chars)!=len(ends) or limit<1:
        raise ValueError('caption_alignment_invalid')
    for i,(ch,a,b) in enumerate(zip(chars,starts,ends)):
        if not isinstance(ch,str) or len(ch)!=1 or not isinstance(a,(int,float)) or not isinstance(b,(int,float)) or not math.isfinite(a+b) or a<0 or b<a or (i and a<starts[i-1]):
            raise ValueError('caption_alignment_invalid')
    text=''.join(chars); cues=[]; first=None; last=None
    def flush():
        nonlocal first,last
        if first is not None:
            end=last
            while end>first and text[end-1].isspace(): end-=1
            if end>first: cues.append({'text':text[first:end], 'start':starts[first], 'end':ends[end-1]})
        first=last=None
    for token in TOKEN.finditer(text):
        value=token.group()
        if value.isspace():
            if first is not None: last=token.end()
            continue
        if len(value)>2*limit: raise ValueError('caption_word_too_long')
        if first is not None and token.end()-first>limit: flush()
        if first is None: first=token.start()
        last=token.end()
        # Decimal/version punctuation inside words stays part of its token.
        if value[-1] in '。！？.!?\n': flush()
    flush()
    return cues
