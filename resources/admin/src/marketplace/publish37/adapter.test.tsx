// @vitest-environment jsdom
import React,{act,useState} from 'react';
import {createRoot} from 'react-dom/client';
import {expect,it,vi} from 'vitest';
import {ShopeePublishingRequirements} from '../ShopeePublishingRequirements';
vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT',true);
it('selects real preset IDs and preserves mandatory channel and shipping size identity',async()=>{
 const updateAttribute=vi.fn(),updateChannel=vi.fn();
 const review:any={storeId:'2',loading:false,error:'',retry:vi.fn(),values:{},fields:[{attribute_id:10,mandatory:false,attribute_info:{name:'Material',input_type:4,max_value_count:2},attribute_value_list:[{value_id:501,name:'Cotton'},{value_id:502,name:'Silk'}]}],channels:[{logistics_channel_id:91,logistics_channel_name:'Actual required channel',force_enable:true,fee_type:'NONE'},{logistics_channel_id:92,logistics_channel_name:'Actual size channel',fee_type:'SIZE_SELECTION',size_list:[{size_id:33,name:'Provider parcel'}]}],selectedChannels:{91:{logistic_id:91},92:{logistic_id:92}},updateAttribute,updateChannel};
 const container=document.createElement('div');document.body.append(container);const root=createRoot(container);
 await act(async()=>root.render(<ShopeePublishingRequirements review={review}/>));
 expect(container.textContent).not.toContain('SPX Express');expect(container.textContent).not.toContain('Connection Type');
 const required=container.querySelector('[aria-label="Actual required channel"]') as HTMLInputElement;
 expect(required.disabled).toBe(true);expect(required.checked).toBe(true);
 const size=container.querySelector('select') as HTMLSelectElement;expect(size.value).toBe('');
 await act(async()=>{size.value='33';size.dispatchEvent(new Event('change',{bubbles:true}));});
 expect(updateChannel).toHaveBeenCalledWith(92,expect.objectContaining({logistic_id:92,size_id:33}));
 const trigger=[...container.querySelectorAll('div')].find(e=>e.textContent==='Choose Material...'&&e.className.includes('min-h-'))!;
 await act(async()=>(trigger as HTMLElement).click());
 const option=[...container.querySelectorAll('button,div,label')].find(e=>e.textContent==='Cotton'&&e.tagName==='LABEL') as HTMLElement;
 expect(option).toBeTruthy();await act(async()=>option.click());
 expect(updateAttribute).toHaveBeenCalledWith(10,[{value_id:501,original_value_name:'Cotton',value_unit:''}]);
 await act(async()=>root.unmount());container.remove();
});
