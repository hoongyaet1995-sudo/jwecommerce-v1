"""Offline prototype: AI writes original content; system owns template and execution."""
import copy,hashlib,json
from jsonschema import Draft202012Validator

def obj(p):return {'type':'object','properties':p,'required':list(p),'additionalProperties':False}
S={'type':'string'}
detail=obj({'title':S,'copy':S,'asset_id':S,'position_x':{'type':'number','minimum':0,'maximum':1},'position_y':{'type':'number','minimum':0,'maximum':1},'scale':{'type':'number','minimum':1,'maximum':1.5}})
SCHEMA=obj({'template_id':S,'product_id':S,'language':{'type':'string','enum':['zh','en']},
 'copy':obj({'hook':S,'benefit':S,'support':S,'cta':S}),
 'details':{'type':'array','items':detail,'minItems':3,'maxItems':3},
 'narration':obj({'text':S,'voice_id':S,'delivery_brief':S,'planned_start':{'type':'number'},'planned_end':{'type':'number'},'silence_purpose':S}),
 'music':obj({'enabled':{'type':'boolean'},'capability_id':S,'brief':S,'mood':S,'energy':{'type':'number','minimum':0,'maximum':1}}),
 'sound_effects':{'type':'array','items':obj({'capability_id':S,'purpose':S,'start':{'type':'number'}})},
 'claims':{'type':'array','items':obj({'text':S,'source_ids':{'type':'array','items':S}})}})

REGISTRY={'id':'product_showcase_15s','version':'1.0.0-draft','status':'offline_contract_only','industry_binding':None,'narrative':'single-benefit physical-product showcase',
 'frames':360,'fps':24,'seconds':15,'formats':{'landscape':[1920,1080],'portrait':[1080,1920]},
 'slots':[[0,48],[48,144],[144,192],[192,240],[240,288],[288,360]],
 'source_policy':'single-source protected2D; no redraw or unseen views; crops and layout allowed',
 'copy_policy':'AI authors original copy/narration; references ground factual assertions, not exact text equality',
 'audio_tools':{'local-original-instrumental':'Local original soft synthesized-key arpeggio only; energy controls tempo/intensity, no vocals, drums or arbitrary genres. Voice delivery brief is guidance; Multilingualv2 voice tone is not guaranteed by natural-language instructions.'},
 'required_inputs':['selected_template','product_id','product_name','owned_image','product_facts_with_provenance','user_goal','language'],
 'render_status':'generic media-ID staging and local preview renderer wired; fake-response smoke verified; real AI pipeline pending',
 'missing_detail_policy':'AI can use distinct evidenced regions for same feature; no fabricated features; recommend another available template if not feasible',
 'forbidden':['product_redraw','unseen_views','physical_object_animation','invented_claims','arbitrary_code','unapproved_fees']}

def digest(v):return hashlib.sha256(json.dumps(v,sort_keys=True,ensure_ascii=False).encode()).hexdigest()

RESPONSE_SCHEMA=obj({'decision':{'type':'string','enum':['ready','needs_input','unsupported']},'reasons':{'type':'array','items':S},'draft':{'anyOf':[SCHEMA,{'type':'null'}]}})

def validate_response(packet,response):
    if list(Draft202012Validator(RESPONSE_SCHEMA).iter_errors(response)):return {'status':'invalid_response'}
    if response['decision']=='ready':
        if response['draft'] is None:return {'status':'invalid_response'}
        return validate_draft(packet,response['draft'])
    if response['draft'] is not None or not response['reasons']:return {'status':'invalid_response'}
    return {'status':response['decision'],'reasons':response['reasons'],'execution_authorized':False,'template_changed':False}

def validate_draft(packet,draft):
    errors=[f'schema:{e.json_path}' for e in Draft202012Validator(SCHEMA).iter_errors(draft)]
    if errors:return {'status':'invalid_draft','errors':errors}
    if draft['template_id']!=REGISTRY['id'] or packet['selected_template']!=REGISTRY['id']:errors.append('template_mismatch')
    if draft['product_id']!=packet['product_id']:errors.append('product_mismatch')
    if draft['language']!=packet['language']:errors.append('language_mismatch')
    for d in draft['details']:
        if d['asset_id'] not in packet['assets']:errors.append('asset_forbidden')
        elif packet['assets'][d['asset_id']]['product_id']!=packet['product_id']:errors.append('asset_forbidden')
        if not d['title'].strip():errors.append('empty_detail_title')
    for claim in draft['claims']:
        if not claim['source_ids']:errors.append('claim_source_required')
        if any(k not in packet['evidence'] and k not in packet['assets'] for k in claim['source_ids']):errors.append('unknown_claim_source')
    if any(not v.strip() for v in draft['copy'].values()):errors.append('copy_required')
    n=draft['narration']
    if not n['text'].strip() or not n['delivery_brief'].strip():errors.append('narration_required')
    if n['voice_id']!=packet['voices'][draft['language']]:errors.append('voice_not_approved')
    if not 0<=n['planned_start']<n['planned_end']<=15:errors.append('invalid_narration_schedule')
    if n['planned_end']<14 and not n['silence_purpose'].strip():errors.append('silence_purpose_required')
    m=draft['music']
    if m['enabled'] and m['capability_id'] not in packet['music_capabilities']:errors.append('music_capability_unavailable')
    for effect in draft['sound_effects']:
        if effect['capability_id'] not in packet['effect_capabilities']:errors.append('effect_capability_unavailable')
        if not 0<=effect['start']<15:errors.append('effect_timing')
    # All displayed/narrated content must be checked semantically even if claims=[];
    # the model cannot bypass checks by omitting its own assertions list.
    return {'status':'invalid_draft' if errors else 'awaiting_semantic_check','errors':errors,
      'draft_hash':digest(draft),'context_hash':digest(packet),'execution_authorized':False,
      'pending_checks':['all_copy_and_narration_grounding','visual_crop_and_text_fit','actual_tts_duration','music_asset_rights_and_cost','execution_quote']}

def semantic_receipt(packet,draft,status):
    """Fake test helper only, not a semantic evaluator or production approval."""
    return {'mode':'offline_fake_receipt','draft_hash':digest(draft),'context_hash':digest(packet),'status':status}

def compile_plan(packet,draft,receipt):
    valid=validate_draft(packet,draft)
    if valid['status']=='invalid_draft':return valid
    if receipt is None:return valid
    if receipt.get('draft_hash')!=digest(draft) or receipt.get('context_hash')!=digest(packet):return {'status':'stale_semantic_check'}
    if receipt.get('status')!='pass':return {'status':'semantic_rejected'}
    return {'status':'compiled','mode':'offline_simulation','semantic_receipt_mode':receipt.get('mode'),'execution_authorized':False,
      'plan':{'template':copy.deepcopy(REGISTRY),'draft':copy.deepcopy(draft),'assets':copy.deepcopy(packet['assets'])},
      'pending_checks':['real_semantic_evaluator','visual_crop_and_text_fit','actual_tts_duration','music_asset_rights_and_cost','execution_quote']}

def check_audio(seconds,measured_duration,start):
    fits=isinstance(measured_duration,(int,float)) and measured_duration>0 and start>=0 and start+measured_duration<=seconds-.3
    return {'status':'fits' if fits else 'overrun_no_auto_regeneration','paid_retry_allowed':False}

def change_scope(k):
    return {'narration':['voice','audio_fit','mix','mux'],'voice':['voice','audio_fit','mix','mux'],
      'copy':['text_render','mux'],'music':['music','mix','mux'],'destination':[],
      'product':['plan','semantic_check','render','voice','mix','mux']}[k]

def fixture():
    packet={'selected_template':REGISTRY['id'],'product_id':'fixture-shirt','product_name':'衬衫概念样品','language':'zh','user_goal':'吸引用户查看款式细节',
      'assets':{'front':{'product_id':'fixture-shirt','sha256':'FAKE_FIXTURE_HASH','provenance':'ai_concept','width':1400,'height':1800}},
      'evidence':{'obs-collar':{'kind':'image_observation','text':'可见衣领轮廓','source_asset':'front'},'obs-pockets':{'kind':'image_observation','text':'正面两个口袋','source_asset':'front'}},
      'voices':{'zh':'ByhETIclHirOlWnWKhHc','en':'hpp4J3VqNfWAUOO0d1Us'},'music_capabilities':['local-original-instrumental'],'effect_capabilities':[]}
    draft={'template_id':REGISTRY['id'],'product_id':packet['product_id'],'language':'zh',
      'copy':{'hook':'简单穿，也能穿出自己的风格。','benefit':'让衣领与口袋细节，成为日常的亮点。','support':'从可见设计开始认识这件衬衫。','cta':'看看这件衬衫'},
      'details':[{'title':t,'copy':'','asset_id':'front','position_x':.5,'position_y':y,'scale':1.2} for t,y in [('衣领线条',0),('双口袋设计',.4),('口袋细节',.7)]],
      'narration':{'text':'简单穿，也能穿出自己的风格。衣领线条，双口袋设计，从细节认识这件衬衫。喜欢这个风格？点开看看。','voice_id':packet['voices']['zh'],'delivery_brief':'明亮自然，有精神；短句、重点清楚，避免拖长尾音。','planned_start':.4,'planned_end':13,'silence_purpose':'结尾保留商品与行动提示，音乐收束。'},
      'music':{'enabled':True,'capability_id':'local-original-instrumental','brief':'轻盈键盘节奏，无人声；口播时压低，结尾收束。','mood':'明亮轻松','energy':.55},'sound_effects':[],
      'claims':[{'text':'双口袋设计','source_ids':['obs-pockets']}]}
    return packet,draft
