"""Offline cached tokenizer; conservative planning budget, not provider billing."""
import json,math,socket,sys
from pathlib import Path
import tiktoken
def no_network(*args,**kwargs): raise RuntimeError('cached_tokenizer_missing')
socket.socket=no_network
text=Path(sys.argv[1]).read_text(encoding='utf-8')
count=len(tiktoken.get_encoding('o200k_base').encode(text,disallowed_special=()))
print(json.dumps({'tokens':count,'budget_upper_estimate':math.ceil(count*1.2)+800,'encoding':'o200k_base','billing':False}))
