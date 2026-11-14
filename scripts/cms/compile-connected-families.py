"""Compile reviewed visual sources into React elements and canonical data, never HTML storage."""
from pathlib import Path
from bs4 import BeautifulSoup, NavigableString, Comment
import json, re, hashlib, collections, shutil

ROOT=Path(__file__).resolve().parents[2]
SRC=ROOT/'public/jw-builder-sample/preview'
OUT=ROOT/'resources/admin/src'
manifest=json.loads((ROOT/'public/jw-builder-sample/manifest.json').read_text(encoding='utf8'))
mapping=json.loads((ROOT/'resources/cms/connected-family-mapping.v1.json').read_text(encoding='utf8'))
types={f['id']:f['type'] for f in mapping['families']}
J=lambda x:json.dumps(x,ensure_ascii=True,separators=(',',':'))
shape_groups={}; diagrams=[]; layout_meta={}; snapshots={}; source_map={}; functions=[]
layout_names={'hero':['hero-centered','hero-split'],'cards':['cards-grid','cards-bento','cards-compact'],'story':['story-rows','story-split'],'process':['process-orbit','process-cards'],'metrics':['metrics-bento','metrics-row'],'comparison':['comparison-narrative'],'tabs':['preview-tabs'],'stack':['sticky-stack'],'logos':['logo-marquee'],'faq':['faq-list'],'cta':['closing-cta']}

def shape(n):
 if not getattr(n,'name',None):return ''
 return n.name+'.'+'.'.join(c for c in n.get('class',[]) if c not in ['is-active','reveal'])+'('+''.join(shape(c) for c in n.children)+')'

def layout(s,node):
 f=s['family']; classes=' '.join(node.get('class',[])); text=str(node)
 if f=='hero':return 'hero-centered' if 'hero' in node.get('class',[]) else 'hero-split'
 if f=='cards':return 'cards-bento' if any(v in text for v in ['bento','creative-workspace','product-story','live-host-panel','multi-insight-panel']) else 'cards-compact' if any(v in text for v in ['capability-grid','capability-card','feature-mini','narrow-heading']) else 'cards-grid'
 if f=='story':return 'story-rows' if 'data-collection' in text else 'story-split'
 if f=='process':return 'process-orbit' if 'orbit' in text or 'class="process ' in text else 'process-cards'
 if f=='metrics':return 'metrics-bento' if 'outcomes' in classes else 'metrics-row'
 return layout_names[f][0]

def asset(value):
 if not isinstance(value,str):return value
 if '/assets/' in value or value.startswith('assets/'):
  return '/cms/connected/assets/'+value.split('assets/',1)[1]
 return value

def prop_name(k):
 return {'class':'className','for':'htmlFor','tabindex':'tabIndex','viewbox':'viewBox','preserveaspectratio':'preserveAspectRatio','stroke-width':'strokeWidth','stroke-linecap':'strokeLinecap','stroke-linejoin':'strokeLinejoin','fill-rule':'fillRule','clip-rule':'clipRule','colspan':'colSpan','rowspan':'rowSpan','crossorigin':'crossOrigin','datetime':'dateTime','srcset':'srcSet','playsinline':'playsInline','autoplay':'autoPlay','allowfullscreen':'allowFullScreen'}.get(k,k)

for page in manifest['pages']:
 path=SRC/('index.html' if page['id']=='home' else page['id']+'/index.html')
 soup=BeautifulSoup(path.read_text(encoding='utf8'),'html.parser')
 sections=[];anchors={}
 for source in page['sections']:
  raw_node=soup.select_one('[data-section="'+source['id']+'"]')
  for anchor in [raw_node]+list(raw_node.find_all(id=True)):
   if anchor.get('id'):anchors[anchor['id']]=source['id']
  if source['family']=='pricing':sections.append({'pricingSource':source['id']});continue
  node=soup.select_one('[data-section="'+source['id']+'"]')
  # Explicitly excluded capabilities: no industry title-icon capsule or description tags.
  # The approved reference files remain unchanged; other layout illustrations retain their icons.
  if page['id']=='home' and source['family']=='hero':
   for decoration in list(node.select('.headline-chip,.hero-title-icon')):decoration.decompose()
   for tag in list(node.select('.hero-subcopy mark')):tag.unwrap()
  # Global footer belongs to Shell, never to a CMS CTA region.
  for shell_node in list(node.select('footer,.footer-brand,.footer-legal')):shell_node.decompose()
  key=(source['family'],hashlib.sha256(shape(node).encode()).hexdigest())
  new=key not in shape_groups
  if new:
   count=sum(k[0]==key[0] for k in shape_groups)+1
   diagram=source['family']+'-composition-'+str(count)
   shape_groups[key]=diagram; diagrams.append(diagram)
  diagram=shape_groups[key]; family_layout=layout(source,node)
  source_map[source['id']]={'layout':family_layout,'diagram':diagram}
  context={'commerce':'commerce','connected-operations':'operations','custom-workflows':'workflow','inventory-fulfilment':'fulfilment','website-builder':'website'}.get(page['id'],'default')
  section={'id':source['id'],'type':types[source['family']],'isVisible':True,'containerMode':'page','motionPreset':'subtle-lift','sectionBorder':'none','connectedLayout':family_layout,'connectedDiagram':diagram,'connectedVisualContext':context,'surface':'page','items':[]}
  fields={f['id']:f for f in source['fields']}; field_bindings={}; group_nodes={}; group_metadata=[]; details={}; detail_meta=[]; field_usage=collections.Counter()
  # Every repeatable group uses shared canonical SectionItem values and stable identities.
  for gi,group in enumerate(source.get('collections',[])):
   g='c'+str(gi); group_nodes[group['id']]=(g,group)
   group_fields=set()
   for vi,item in enumerate(group['items']):
    values={k:asset(v) for k,v in item['values'].items() if k in ['title','description','subtitle','badge','value','imageUrl','mediaAlt','buttonText','linkUrl','icon','eyebrow','category','label','suffix']}
    values.setdefault('title','')
    for k,v in item['values'].items():
     if k.endswith('.alt'):values['mediaAlt']=v
     if k.endswith('.linkUrl'):values['linkUrl']=v
    section['items'].append({'id':item['id'],'layoutGroup':g,'layoutVariant':vi,**values})
    for f in group['fields'].get(item['templateId'],[]):
     if f['key'].startswith('slots.'):
      if f.get('fieldId'):
       slot=diagram+'-entry-'+str(vi)+'-'+f['key'].replace('.','-'); prop='imageUrl' if f['role']=='image' else 'eyebrow' if f['role']=='eyebrow' else 'title' if f['role']=='itemTitle' else 'description'
       details[slot]={'id':source['id']+'-'+slot,'layoutGroup':'details','layoutSlot':slot,'title':'',prop:asset(f.get('value',item['values'].get(f['key'],'')))}
       if prop=='imageUrl':details[slot]['mediaAlt']=item['values'].get(f['key']+'.alt','')
       detail_meta.append({'slot':slot,'label':(values.get('title') or group['title'])+' — '+f['label'],'field':prop,'role':f['role']})
       field_bindings[f['fieldId']]=('detail',slot,prop)
      continue
     keyname='mediaAlt' if f['key'].endswith('.alt') else 'linkUrl' if f['key'].endswith('.linkUrl') else f['key']
     if f.get('fieldId'):field_bindings[f['fieldId']]=('item',keyname)
     group_fields.add(keyname)
   group_metadata.append({'id':g,'label':group['title'],'kind':group['kind'],'fields':sorted(group_fields),'min':0,'max':group.get('max',24)})
  if source['family']=='tabs':
   for ti,tab in enumerate(node.select('.capability-tabs button')):
    section['items'].append({'id':source['id']+'-tab-'+str(ti),'layoutGroup':'tabs','title':tab.get('data-title',''),'description':tab.get('data-description',''),'imageUrl':asset(tab.get('data-image','')),'mediaAlt':tab.get('data-title',''),'buttonText':'Explore '+tab.get('data-title',''),'linkUrl':tab.get('data-link','')})
   group_metadata.append({'id':'tabs','label':'Preview tabs','kind':'tab','fields':['title','description','imageUrl','mediaAlt','buttonText','linkUrl'],'min':0,'max':24})
  # Heading, media and actions own standard fields; extra diagram copy is bounded metadata.
  for f in source['fields']:
   if f['id'] in field_bindings:continue
   role=f['role']; field_usage[role]+=1; index=field_usage[role]
   name={'eyebrow':'eyebrow','title':'title','content':'content','image':'mediaUrl','action':'buttonText'}.get(role)
   if role=='action' and index==2:name='secondaryButtonText'
   if name and (index==1 or role=='action' and index==2):
    section[name]=asset(f['value']); field_bindings[f['id']]=('section',name)
    if role=='action':section['secondaryButtonLink' if index==2 else 'buttonLink']=f.get('href','')
    if role=='image':section['mediaAlt']=f.get('alt','')
   else:
    slot=diagram+'-field-'+str(len(details)); prop='imageUrl' if role=='image' else 'title' if role in ['title','itemTitle'] else 'description'
    details[slot]={'id':source['id']+'-'+slot,'layoutGroup':'details','layoutSlot':slot,'title':'',prop:asset(f['value'])}
    if role=='image':details[slot]['mediaAlt']=f.get('alt','')
    if role=='action':details[slot].update(buttonText=f['value'],linkUrl=f.get('href',''))
    detail_meta.append({'slot':slot,'label':f.get('label') or role,'field':prop,'role':role})
    field_bindings[f['id']]=('detail',slot,prop)
  # All source text outside annotated content also becomes editable canonical detail entries.
  serial=0
  static_slots={}
  for text in node.find_all(string=True):
   if isinstance(text,Comment) or not str(text).strip() or text.parent.name in ['script','style']:continue
   if text.find_parent(attrs={'data-field':True}):continue
   if text.find_parent(class_='step-dots') or text.parent.name=='small' and text.parent.find_parent(class_='process-step') or text.parent.name=='span' and 'process-wheel' in text.parent.parent.get('class',[]) or text.parent.name=='strong' and text.parent.find_parent(class_='process-photo'):continue
   slot=diagram+'-label-'+str(serial);serial+=1;static_slots[id(text)]=slot
   details[slot]={'id':source['id']+'-'+slot,'layoutGroup':'details','layoutSlot':slot,'title':'','description':str(text)}
   detail_meta.append({'slot':slot,'label':str(text).strip()[:70],'field':'description','role':'itemContent'})
  section['items']+=list(details.values()); section.setdefault('title','')
  for authored_item in section['items']:
   if 'mediaAlt' in authored_item:section.setdefault('imageSlots',[]).append({'target':'item:'+authored_item['id']+':imageUrl','mediaAlt':authored_item.pop('mediaAlt')})
  sections.append(section)
  if not new:continue
  has_eyebrow=any(v==('section','eyebrow') for v in field_bindings.values())
  has_heading=any(v==('section','title') for v in field_bindings.values())
  meta={'family':source['family'],'layout':family_layout,'type':types[source['family']],'groups':group_metadata,'details':detail_meta,'textRoles':sorted(set(('number' if f['role']=='value' else f['role']) for f in source['fields'] if f['role'] not in ['image','action'])|({'eyebrow'} if has_heading else set())),'sectionFields':sorted(set(v[1] for v in field_bindings.values() if v[0]=='section')|({'eyebrow'} if has_heading else set()))}
  for owner,dependent in [('mediaUrl','mediaAlt'),('buttonText','buttonLink'),('secondaryButtonText','secondaryButtonLink')]:
   if owner in meta['sectionFields']:meta['sectionFields'].append(dependent)
  meta['actions']={'primary':{'button':False,'link':source['family']=='tabs'},'secondary':{'button':False,'link':False}}
  for action_node in node.select('[data-role="action"]'):
   binding=field_bindings.get(action_node.get('data-field'));channel='secondary' if binding==('section','secondaryButtonText') else 'primary'
   meta['actions'][channel]['button' if 'button' in action_node.get('class',[]) else 'link']=True
  layout_meta[diagram]=meta

  def value(binding,item='item'):
   if binding[0]=='section':return 's.'+binding[1]
   if binding[0]=='item':return item+'?.['+J(binding[1])+']'
   return 'detail(s,'+J(binding[1])+')?.['+J(binding[2])+']'

  def attrs(n,item=None):
   props=[]
   for k,v in n.attrs.items():
    if k in ['style','data-field','data-role','data-item-field','data-repeat-item','data-collection','data-section'] or k.startswith('on') or k.startswith('data-item-'):continue
    if k in ['data-image','data-title','data-description','data-link']:continue
    if isinstance(v,list):v=' '.join(v)
    if k=='class':v=' '.join(c for c in v.split() if c not in ['reveal','magnetic','tilt-card'])
    if k=='class' and 'process-step' in v.split() and item:
     props.append('className:'+J(' '.join(c for c in v.split() if c!='is-active'))+'+('+item+'.id===activeStepId?" is-active":"")');continue
    if k=='id':props.append('id:nodeId(s,'+J(v)+')');continue
    if k in ['aria-controls','aria-labelledby']:
     refs=[]
     for ref in v.split():
      target=node.find(id=ref);owner=field_bindings.get(target.get('data-field')) if target else None
      refs.append('('+value(owner,item or 'undefined')+'?nodeId(s,'+J(ref)+'):undefined)' if owner else 'nodeId(s,'+J(ref)+')')
     props.append(J(k)+':['+','.join(refs)+'].filter(Boolean).join(" ")||undefined');continue
    if k in ['src','poster']:v=asset(v)
    if k=='href' and v.startswith('#'):props.append('href:"#"+nodeId(s,'+J(v[1:])+')');continue
    if k in ['hidden','open','loop','muted','controls','autoPlay','playsinline']:v=True
    props.append(J(prop_name(k))+':'+J(v))
   if n.get('style'):
    style={}
    for kv in n['style'].split(';'):
     if ':' not in kv:continue
     k,v=kv.split(':',1);k=k.strip();k=re.sub(r'-([a-z])',lambda m:m[1].upper(),k) if not k.startswith('--') else k;style[k]=v.strip()
    props.append('style:'+J(style))
   if n.has_attr('data-repeat-item') and item:props.append('key:'+item+'.id');props.append('"data-cms-item":'+item+'.id')
   if n.get('data-collection') in group_nodes:
    gid,definition=group_nodes[n['data-collection']]
    props.append('"data-collection-count":group(s,'+J(gid)+').length')
    if definition['kind'] not in ['orbit','logo','stack']:
     original_class=' '.join(c for c in n.get('class',[]) if c not in ['reveal','magnetic','tilt-card'])
     props=[p for p in props if not p.startswith('"className":') and not p.startswith('className:')]
     props.append('className:'+J(original_class)+'+(group(s,'+J(gid)+').length!=='+str(len(definition['items']))+'?" sample-collection-reflow":"")')
   if 'solution-card' in n.get('class',[]) and item:
    stack_group=next((gid for gid,data in group_nodes.values() if data['kind']=='stack'),None)
    if stack_group:
     props=[p for p in props if not p.startswith('style:')]
     props.append('style:{"--stack-index":group(s,'+J(stack_group)+').findIndex(peer=>peer.id==='+item+'.id),marginBottom:group(s,'+J(stack_group)+').at(-1)?.id==='+item+'.id?0:90} as React.CSSProperties')
   return props

  def emit(n,item=None,skip_repeat=False):
   if isinstance(n,Comment):return 'null'
   if isinstance(n,NavigableString):
    if id(n) in static_slots:return 'detail(s,'+J(static_slots[id(n)])+')?.description'
    return J(str(n))
   if n.name in ['script','style']:return 'null'
   if 'step-dots' in n.get('class',[]):return 'h("div",{className:"step-dots",role:"tablist"},orbit.map(entry=>h("button",{key:entry.id,type:"button",role:"tab","aria-label":entry.title,"aria-selected":entry.id===activeStepId,className:entry.id===activeStepId?"is-active":"",onClick:()=>setSelectedStep(entry.id)})))'
   if n.name=='small' and n.find_parent(class_='process-step') and item:return 'h("small",{},String(orbit.findIndex(peer=>peer.id==='+item+'.id)+1).padStart(2,"0"))'
   if n.name=='span' and 'process-wheel' in n.parent.get('class',[]):return 'h("span",{},String(orbit.length)+" connected steps")'
   if n.name=='strong' and n.find_parent(class_='process-photo'):return 'h("strong",{},String(orbit.length))'
   if 'capability-tabs' in n.get('class',[]):
    return 'h("div",{className:"capability-tabs",role:"tablist"},group(s,"tabs").map((entry,index)=>h("button",{key:entry.id,type:"button",role:"tab","aria-selected":activeTab===entry.id,className:activeTab===entry.id?"is-active":"",onClick:()=>setActiveTab(entry.id)},h("small",{},String(index+1).padStart(2,"0")),entry.title)))'
   if n.get('id')=='capability-image':return 'activeEntry?.imageUrl?h(ScopedImage,{section:s,targetId:"item:"+activeEntry.id+":imageUrl",src:activeEntry.imageUrl,alt:activeEntry.title||""}):null'
   if n.get('id')=='capability-description':return 'activeEntry?.description?h("p",{"data-cms-text":"itemContent","data-cms-item":activeEntry.id,style:typographyStyle(s,"itemContent")},activeEntry.description):null'
   if n.get('id')=='capability-link':return 'activeEntry?.buttonText&&linkHref(activeEntry?.linkUrl||"")?h("a",{href:linkHref(activeEntry.linkUrl||""),"data-cms-button":"primary","data-cms-button-kind":"link","data-cms-action":"link","data-cms-item":activeEntry.id,onClick:(event:React.MouseEvent)=>action(event,activeEntry.linkUrl,editing)},activeEntry.buttonText):null'
   repeat=n.get('data-repeat-item')
   if repeat and not skip_repeat:
    # Collection roots may contain an illustrative sibling, so replace only repeat roots.
    parent=n.parent
    siblings=[c for c in parent.children if getattr(c,'attrs',{}).get('data-repeat-item')]
    if siblings and n is not siblings[0]:return 'null'
    collection=next((g for gid,(g,data) in group_nodes.items() if any(i['id']==repeat for i in data['items'])),None)
    if collection is not None:
     variants=[]
     for index,sib in enumerate(siblings):variants.append('case '+str(index)+':return '+emit(sib,'entry',True)+';')
     return 'group(s,'+J(collection)+').map(entry=>{switch(entry.layoutVariant??0){'+''.join(variants)+'default:return '+emit(siblings[0],'entry',True)+';}})'
   binding=field_bindings.get(n.get('data-field')); role=n.get('data-role'); props=attrs(n,item)
   tag=J(n.name)
   if binding:
    val=value(binding,item or 'undefined')
    if role=='eyebrow' and binding[0]=='section':return 'h(SectionEyebrow,{section:s})'
    if role=='image':
     props=[p for p in props if not p.startswith('"src":') and not p.startswith('"alt":')]
     target='"mediaUrl"' if binding[0]=='section' else '"item:"+'+(item+'.id' if binding[0]=='item' and item else 'detail(s,'+J(binding[1])+')?.id')+'+":imageUrl"'
     props+=['section:s','targetId:'+target,'src:'+val+'||undefined','alt:'+(('s.mediaAlt' if binding[0]=='section' else (item or 'undefined')+'?.title') if binding[0]!='detail' else 'detail(s,'+J(binding[1])+')?.title')+'||""']
     return 'h(ScopedImage,{'+','.join(props)+'})'
    if role!='action':props.append('"data-cms-text":'+J('number' if role=='value' else role if role in ['eyebrow','title','content','itemTitle','itemContent'] else 'itemContent'))
    if binding[0]=='detail':props.append('"data-cms-item":detail(s,'+J(binding[1])+')?.id')
    elif item:props.append('"data-cms-item":'+item+'.id')
    if role=='action':
     href='s.secondaryButtonLink' if binding[0]=='section' and binding[1]=='secondaryButtonText' else 's.buttonLink' if binding[0]=='section' else (item+'?.linkUrl' if binding[0]=='item' and item else 'detail(s,'+J(binding[1])+')?.linkUrl')
     props=[p for p in props if not p.startswith('"href":') and not p.startswith('href:')]
     props+=['href:linkHref('+href+'||"")||undefined','onClick:(event:React.MouseEvent)=>action(event,'+href+',editing)','"data-cms-button":'+J('secondary' if 'secondary' in val else 'primary'),'"data-cms-button-kind":'+J('button' if 'button' in n.get('class',[]) else 'link'),'"data-cms-action":'+J('button' if 'button' in n.get('class',[]) else 'link')]
    else:
     props=[p for p in props if not p.startswith('style:')]+['style:{'+('...'+next((p[6:] for p in props if p.startswith('style:')),'{}')+',')+'...typographyStyle(s,'+J('number' if role=='value' else role if role in ['title','content','itemTitle','itemContent','eyebrow'] else 'itemContent')+')}']
    # Preserve reviewed inline spans, marks, linebreaks and decoration on initial content.
    inline=val if n.has_attr('data-count') else 'h(AuthoredInline,{value:'+val+',original:'+J(fields[n['data-field']]['value'])+'},'+','.join(emit_inline(c) for c in n.children)+')'
    if binding==('section','title'):inline='(s.titleEmphasis?.length?h(SectionTitle,{section:s}):'+inline+')'
    rendered='('+val+'?h('+tag+',{'+','.join(props)+'},'+inline+'):null)'
    if binding==('section','title') and not has_eyebrow:rendered='h(React.Fragment,{},h(SectionEyebrow,{section:s}),'+rendered+')'
    return rendered
   children=[emit(c,item) for c in n.children]
   return 'h('+tag+',{'+','.join(props)+'}'+(','+','.join(children) if children else '')+')'

  def emit_inline(n):
   if isinstance(n,Comment):return 'null'
   if isinstance(n,NavigableString):return J(str(n))
   return 'h('+J(n.name)+',{'+','.join(attrs(n))+'}'+(','+','.join(emit_inline(c) for c in n.children) if list(n.children) else '')+')'

  # Build-native syntax only: JSX-equivalent React element calls, no serialized DOM.
  functions.append('case '+J(diagram)+':return '+emit(node)+';')
 snapshots[page['id']]={'name':page['name'],'bodyClass':' '.join(soup.body.get('class',[])),'anchors':anchors,'sections':sections}

(OUT/'cms/connectedFamilyMetadata.json').write_text(json.dumps(layout_meta,indent=2,ensure_ascii=False),encoding='utf8')
(OUT/'cms/connectedFamilySnapshots.json').write_text(json.dumps(snapshots,indent=2,ensure_ascii=False),encoding='utf8')
(ROOT/'output/jw-cms-integration-20261114/connected-source-map.json').write_text(json.dumps(source_map,indent=2),encoding='utf8')
header='''// Generated from reviewed source by compile-connected-families.py. Edit the compiler, not stored markup.
import React from 'react';
import {SectionEyebrow} from './SectionEyebrow';
import {SectionTitle} from './SectionTitle';
import {ScopedImage} from './ScopedImage';
import {typographyStyle} from '../../cms/inspectorCapabilities';
import {shopNavigationHref as linkHref} from '../../cms/shopNavigation';
import type {ConnectedSection} from '../../cms/connectedFamilies';
import {connectedPassport} from '../../cms/connectedFamilies';
import {AuthoredInline,action,detail,group,nodeId} from './ConnectedNativePrimitives';
import './ConnectedNative.css';
const h=React.createElement;
export function ConnectedNativeComposition({section:s,editing=false}:{section:ConnectedSection;editing?:boolean}){const tabs=group(s,'tabs');const [selectedTab,setActiveTab]=React.useState('');const activeEntry=tabs.find(item=>item.id===selectedTab)||tabs[0];const activeTab=activeEntry?.id;const orbitGroup=connectedPassport(s)?.groups.find(g=>g.kind==='orbit')?.id;const orbit=orbitGroup?group(s,orbitGroup):[];const [selectedStep,setSelectedStep]=React.useState('');const activeStepId=orbit.some(item=>item.id===selectedStep)?selectedStep:orbit[0]?.id;const stepIds=orbit.map(item=>item.id).join('|');React.useEffect(()=>{if(orbit.length<2)return;const timer=setInterval(()=>{if(document.hidden||matchMedia('(prefers-reduced-motion:reduce)').matches)return;setSelectedStep(current=>{const index=orbit.findIndex(item=>item.id===current);return orbit[((index<0?0:index)+1)%orbit.length].id;});},4200);return()=>clearInterval(timer);},[stepIds]);switch(s.connectedDiagram){
'''
(OUT/'components/pages/ConnectedNativeComposition.tsx').write_text(header+'\n'.join(functions)+'\ndefault:return null;}}\n',encoding='utf8')
# Keep assets first party and independent from the sample reference tree.
for source_dir in (SRC/'assets').iterdir():
 if source_dir.name in ['css','js']:continue
 destination=ROOT/'public/cms/connected/assets'/source_dir.name
 if source_dir.is_dir():shutil.copytree(source_dir,destination,dirs_exist_ok=True)
 else:shutil.copy2(source_dir,destination)
css=(SRC/'assets/css/style.css').read_text(encoding='utf8')
reflow=(ROOT/'public/jw-builder-sample/structured-preview.css').read_text(encoding='utf8').split('[data-role][hidden]')[0]
reflow=re.sub(r'\.mobile-plan[^{}]*\{[^{}]*\}','',reflow)
css+='\n'+reflow
fonts=''
for fontfile in (SRC/'assets/fonts').glob('*.css'):
 fontcss=fontfile.read_text(encoding='utf8')
 fontcss=re.sub(r'url\(([\"\']?)(?!https?:|/)',r'url(\1/cms/connected/assets/fonts/',fontcss)
 fonts+=fontcss+'\n'
for fontname in ['Stack Sans Headline','Inter Display','Switzer','Inter']:
 css=css.replace('"'+fontname+'"','"CMS Connected '+fontname+'"')
 fonts=fonts.replace("'"+fontname+"'","'CMS Connected "+fontname+"'")
# Rename animation identifiers, preventing collisions with Shell and legacy templates.
for name in re.findall(r'@keyframes\s+([\w-]+)',css):css=re.sub(r'(?<![\w-])'+re.escape(name)+r'(?![\w-])','cmsConnected-'+name,css)
css=re.sub(r'(?<![\w-])(?:html|body|:root)(?=[\s{.:#>\[])',':scope',css)
css=re.sub(r'@media\s*(\((?:max|min)-width:\s*\d+px\))',r'@container cms-connected \1',css)
# Container self styles cannot be queried; source responsive root tokens belong on native section children.
css=re.sub(r'(@container cms-connected[^{}]+\{\s*):scope\s*\{',r'\1:scope > section {',css)
# Semantic palette roles replace the sample paint identifiers, including nested illustration paints.
css=re.sub(r'var\(--paint-([0-9a-f]{6}),\s*(#[0-9a-fA-F]{6})\)',lambda m:'var(--cms-primary,#ffa1ed)' if m[1] in ['ffa1ed','f5a3e7','ff9feb'] else 'var(--cms-secondary,#94ff55)' if m[1] in ['94ff55','c2ff7c','c6ff7c','c5ff7a'] else 'var(--cms-contrast,#0d1b18)' if m[1] in ['0d1b18','0d1715','0a0b0b'] else m[2],css)
css=re.sub(r'url\(([\"\']?)(?:\.\./)+',r'url(\1/cms/connected/assets/',css)
# Native reveal is owned by shared CMS motion. Do not leave source animation observers required.
css+='\n:scope .reveal{opacity:1;transform:none} :scope .cms-eyebrow-region{display:inline-flex} :scope [data-cms-text]{white-space:pre-line}\n'
# Additional authored entries reflow; source diagrams retain their reviewed placement for initial counts.
css+=':scope [data-cms-item]{min-width:0} :scope .process-steps{flex-wrap:wrap} :scope .service-feature-grid{grid-auto-rows:auto}\n'
# Section geometry is shared with Page Settings and explicit employee overrides.
css+=':scope {--page-gutter:var(--cms-page-gutter,36px);--max:var(--cms-page-width,100%)} :scope[data-padding] > section.section-pad{padding-block:var(--connected-padding)} :scope[data-text-alignment] :is(.hero-copy,.subpage-hero-copy,.section-heading,.narrow-heading,.faq-heading){text-align:var(--connected-alignment)}\n'
css+=':scope[data-explicit-surface] > section{background:var(--connected-surface)!important;color:var(--ink)}\n'
css+=':scope :is(.dark-panel,.performance-card,.outcome-wide,.process-wheel){color:var(--cms-on-contrast)} :scope .dark-panel{background:var(--cms-contrast)} :scope .dark-panel .cms-eyebrow{color:var(--cms-on-contrast);border-color:color-mix(in srgb,var(--cms-on-contrast) 25%,transparent)} :scope .dark-panel :is(.capability-preview p,.capability-tabs button){color:color-mix(in srgb,var(--cms-on-contrast) 75%,transparent)} :scope .dark-panel .capability-tabs button:is(:hover,.is-active){background:color-mix(in srgb,var(--cms-on-contrast) 8%,var(--cms-contrast));color:var(--cms-on-contrast)}\n'
compiled_css=fonts+'\n@scope (.cms-connected-native) {\n'+css+'\n}\n'
# Vite resolves these files during build; emitted public URLs have no sample-tree dependency.
compiled_css=compiled_css.replace('/cms/connected/assets/','../../../../../public/cms/connected/assets/')
(OUT/'components/pages/ConnectedNative.css').write_text(compiled_css,encoding='utf8')
print(json.dumps({'pages':len(snapshots),'regions':sum(len(p['sections']) for p in snapshots.values()),'nativeShapes':len(shape_groups),'layouts':sorted(set(v['layout'] for v in source_map.values()))}))
