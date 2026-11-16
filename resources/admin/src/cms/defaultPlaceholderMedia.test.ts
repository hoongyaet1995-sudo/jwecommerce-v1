// @vitest-environment jsdom
import {it,expect,vi} from 'vitest';
import {applyPlaceholderMedia} from './defaultPlaceholderMedia';
it('keeps authored alt text when a Neutral placeholder is resized',()=>{
 const root=document.createElement('div'),img=document.createElement('img');root.append(img);document.body.append(root);
 img.src='/images/cms/defaults/neutral/photo-1000-center.svg';img.alt='Employee accessible description';
 vi.spyOn(img,'getBoundingClientRect').mockReturnValue({x:0,y:0,left:0,top:0,right:640,bottom:400,width:640,height:400,toJSON:()=>({})});
 applyPlaceholderMedia(root,'neutral');expect(img.alt).toBe('Employee accessible description');expect(img.src).toContain('data:image/svg');
 root.remove();
});
