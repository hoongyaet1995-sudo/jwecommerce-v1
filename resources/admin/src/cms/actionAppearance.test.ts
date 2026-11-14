import {it,expect} from 'vitest';
import {actionAppearanceCss,actionCapabilities,isButtonColors} from './actionAppearance';
import {stylePresets,resolveStyledSection} from './pageStyle';
it('scopes text and button colors independently and never rewrites legacy palette fields',()=>{
 const s:any={id:'quote"x',type:'shop_navigation',title:'Keep',isVisible:true,shopNavigationPreset:'fashion-banners',customColors:{primaryBtnText:'#111111',secondaryBtnText:'#eeeeee'},typography:{itemTitle:{color:'#123456',colorOverride:true}},buttonColors:{primaryText:'#abcdef'}};
 const before=JSON.stringify(s);const css=actionAppearanceCss(s,resolveStyledSection(s,{palette:stylePresets[0].palette}));
 expect(css).toContain('[data-cms-text="itemTitle"]{color:#123456!important;}');expect(css).toContain('[data-cms-button="primary"]{color:#abcdef!important;}');expect(JSON.stringify(s)).toBe(before);
 expect(css).not.toContain('background:');expect(css).toContain('\\22 ');
});
it('keeps hover and geometry opt-in, separates radius from size, excludes text links',()=>{
 const s:any={id:'s',type:'contact_form',title:'Keep',isVisible:true};const page={palette:stylePresets[0].palette};
 expect(actionAppearanceCss(s,s,page)).toBe('');
 expect(actionAppearanceCss({...s,buttonRadius:6},s,page)).toContain('border-radius:6px');expect(actionAppearanceCss({...s,buttonRadius:6},s,page)).not.toContain('min-height');
 const css=actionAppearanceCss({...s,buttonSize:'large',buttonColors:{primaryHover:'#123456'}},s,page);
 expect(css).toContain('min-height:52px');expect(css).toContain('padding-block:16px');expect(css).toContain('[data-cms-button-kind="button"]');expect(css).toContain(':hover{background:#123456');
 expect(actionAppearanceCss(s,s,{...page,buttonHover:true})).toContain(':hover{background:'+page.palette.hover);
 expect(actionAppearanceCss(s,s,{...page,buttonHover:true,buttonHoverColor:'#abcdef'})).toContain(':hover{background:#abcdef');
});
it('keeps only bounded canonical color values',()=>{expect(isButtonColors({primaryBg:'#123456',secondaryHover:'#abcdef'})).toBe(true);expect(isButtonColors({primaryBg:'url(https://bad)'})).toBe(false);expect(isButtonColors({unknown:'#123456'})).toBe(false)});
it('does not mistake a retained buttonStyle field for the fixed SaaS renderer capability',()=>{
 const s:any={id:'s',type:'media_text_split',title:'SaaS',isVisible:true,layoutPreset:'saas-showcase',buttonStyle:'text-link'};
 expect(actionCapabilities(s).primary.button).toBe(true);
});
