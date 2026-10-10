import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {describe,it,expect} from 'vitest';
import {EditorialStoryCards} from './EditorialStoryCards';
import {createPageTemplateSections} from '../../cms/pageTemplates';
import {imageTargets,updateImageTarget} from '../../cms/imageTargets';
import {repeatedItemFields} from '../../cms/repeatedItemContract';
describe('Editorial Story Cards',()=>{it('renders all entries with owned editable media and safe links',()=>{const s=createPageTemplateSections('home-deco',{},'test').find(section=>section.layoutPreset==='editorial-story-cards')!;s.items!.push({...s.items![0],id:'third',title:'Third story'});const targets=imageTargets(s);expect(targets).toHaveLength(3);expect(targets.every(t=>t.itemId)).toBe(true);expect(repeatedItemFields(s)).toContain('linkUrl');const changed=updateImageTarget(s,targets[1].id,{mediaUrl:'/storage/cms/media/changed.png'});expect(changed.items![0].imageUrl).toBe(s.items![0].imageUrl);expect(changed.items![1].imageUrl).toContain('changed.png');const html=renderToStaticMarkup(<EditorialStoryCards section={s}/>);expect(html).toContain('Third story');expect(html).toContain('Read the guide');expect(html).toContain('data-cms-text="itemContent"');s.items![0].linkUrl='javascript:alert(1)';expect(renderToStaticMarkup(<EditorialStoryCards section={s}/>)).not.toContain('javascript:');});});
