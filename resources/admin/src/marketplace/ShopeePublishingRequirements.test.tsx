// @vitest-environment jsdom
import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {expect,it} from 'vitest';
import {ShopeePublishingRequirements} from './ShopeePublishingRequirements';
it('shows free text without an empty preset list and preserves shipping choices',()=>{
 const review:any={fields:[{attribute_id:1,mandatory:false,attribute_info:{name:'Genre',input_type:2},attribute_value_list:[]}],values:{},channels:[{logistics_channel_id:12,logistics_channel_name:'Sandbox courier'}],selectedChannels:{},error:''};
 const html=renderToStaticMarkup(<ShopeePublishingRequirements review={review}/>);
 expect(html).not.toContain('<select');expect(html).toContain('+ Custom value');expect(html).toContain('Sandbox courier');
 review.fields[0].attribute_info.input_type=1;
 const fixed=renderToStaticMarkup(<ShopeePublishingRequirements review={review}/>);
 expect(fixed).not.toContain('+ Custom value');expect(fixed).toContain('No platform options available');
});
