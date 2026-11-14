import React from 'react';
import {it,expect} from 'vitest';
import {renderToStaticMarkup} from 'react-dom/server';
import {gradientClosing,websiteBuilderClosing} from './gradientClosing';
import {SaasClosing} from '../components/pages/SaasSections';
import {sectionContentFields} from './contentCapabilities';
import {repeatedItemFields} from './repeatedItemContract';
import {textRoles} from './inspectorCapabilities';
it('creates an independent generic CTA with no JW destination or unsupported editors',()=>{
 const a=gradientClosing('a'),b=gradientClosing('b');expect(a.type).toBe('cta_strip');expect(a.ctaPreset).toBe('gradient-panel');expect(a.buttonLink).toBe('');expect(a.containerMode).toBe('page');expect(a.motionPreset).toBe('subtle-lift');
 expect(sectionContentFields(a)).toEqual(['eyebrow','title','content','buttonText','buttonLink']);expect(repeatedItemFields(a)).toEqual([]);expect(textRoles(a)).toEqual(['eyebrow','title','content']);
 a.title='Book your visit';expect(b.title).not.toBe(a.title);expect(JSON.stringify(b)).not.toContain('jwaistudio');
});
it('renders editable title, body and one button without legacy subtitle or second action',()=>{
 const s=websiteBuilderClosing('f01');s.subtitle='Hidden';s.secondaryButtonText='Old second';s.secondaryButtonLink='/old';
 const html=renderToStaticMarkup(<SaasClosing section={s} editing/>);expect(html).toContain('corp-gradient-panel');expect(html).toContain('Let’s build your next chapter online.');expect(html.match(/<a /g)).toHaveLength(1);expect(html).toContain('https://www.jwaistudio.online/pages/contact-jw-studio');expect(html).not.toContain('Old second');expect(html).not.toContain('Hidden');
 s.buttonLink='javascript:alert(1)';expect(renderToStaticMarkup(<SaasClosing section={s} editing/>)).not.toContain('<a ');
});
