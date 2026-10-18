// @vitest-environment jsdom
import {afterEach,expect,it,vi} from 'vitest';
import {searchRequest} from './searchVisibility';
afterEach(()=>vi.unstubAllGlobals());
it('authenticates bodyless Refresh SEO POST requests with CSRF',async()=>{
 document.head.innerHTML='<meta name="csrf-token" content="fixture-csrf">';
 const fetcher=vi.fn().mockResolvedValue(new Response('{}',{status:200}));vi.stubGlobal('fetch',fetcher);
 await searchRequest('/refresh','POST');
 expect(fetcher).toHaveBeenCalledWith('/admin/api/search/refresh',expect.objectContaining({method:'POST',headers:expect.objectContaining({'X-CSRF-TOKEN':'fixture-csrf'})}));
});
