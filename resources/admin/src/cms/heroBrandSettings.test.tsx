import React from 'react';import {it,expect} from 'vitest';import {renderToStaticMarkup} from 'react-dom/server';
import {PageStyleSettings} from '../components/pages/PageStyleSettings';import {generatePalette} from './pageStyle';
it('opens Custom Brand with the current page colours rather than unrelated defaults',()=>{
 const page={id:'qa',title:'QA',slug:'qa',template:'standard',status:'draft',sections:[],createdAt:'',updatedAt:'',style:{palette:generatePalette('#2860ff','#8524ff'),colorSource:'custom'}} as any;
 const html=renderToStaticMarkup(<PageStyleSettings page={page} onChange={()=>{}} onClose={()=>{}}/>);
 expect(html.match(/<input[^>]*aria-label="Brand primary color"[^>]*>/)?.[0]).toContain('value="#2860ff"');
 expect(html).toContain('aria-label="Brand secondary color"');expect(html).toContain('value="#8524ff"');
});
