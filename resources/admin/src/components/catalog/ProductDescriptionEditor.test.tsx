// @vitest-environment jsdom
import React, {act} from 'react';
import {createRoot} from 'react-dom/client';
import {expect,it,vi} from 'vitest';
import {ProductDescriptionEditor} from './ProductDescriptionEditor';

it('renders existing HTML as editable prose without changing it merely on opening', async()=>{
  vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);
  const host=document.createElement('div');document.body.append(host);
  const root=createRoot(host);const change=vi.fn();
  try {
    await act(async()=>root.render(<ProductDescriptionEditor value="<p>Size &amp; fit<br>Black <strong>cotton</strong></p>" onChange={change}/>));
    expect(host.querySelector('[contenteditable="true"]')?.textContent).toContain('Size & fit');
    expect(host.querySelector('strong')?.textContent).toBe('cotton');
    expect(host.querySelector('textarea')).toBeNull();
    expect(change).not.toHaveBeenCalled();
  } finally {await act(async()=>root.unmount());host.remove();vi.unstubAllGlobals();}
});
