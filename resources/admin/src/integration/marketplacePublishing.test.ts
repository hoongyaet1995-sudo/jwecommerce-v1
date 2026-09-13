import {beforeEach,expect,it,vi} from 'vitest';
import {marketplaceRequest} from './marketplaceData';
import {readPublishingSource,preparePublishingImage} from './marketplacePublishing';
vi.mock('./marketplaceData',()=>({marketplaceRequest:vi.fn()}));
beforeEach(()=>vi.resetAllMocks());

it('reads the saved source revision and minor-unit prices without substituting stock',async()=>{
 const source={product_id:4,title:'Saved',description:null,revision:'a'.repeat(64),variants:[{id:8,sku:'JW',price_minor:1599,initial_quantity:0}],images:[{id:6,path:'/storage/cms/media/image.png'}]};
 vi.mocked(marketplaceRequest).mockResolvedValue(source);
 expect(await readPublishingSource(4)).toEqual(source);
 expect(marketplaceRequest).toHaveBeenCalledWith('/products/4/publishing-source');
 vi.mocked(marketplaceRequest).mockResolvedValue({...source,product_id:5});
 await expect(readPublishingSource(4)).rejects.toThrow('source');
});

it('prepares only a saved image and validates all receipt identities',async()=>{
 const receipt={id:9,storeId:2,productId:4,imageId:6,status:'succeeded',providerIdentity:'image-123'};
 vi.mocked(marketplaceRequest).mockResolvedValue(receipt);
 expect(await preparePublishingImage(2,4,6)).toEqual(receipt);
 expect(marketplaceRequest).toHaveBeenCalledWith('/stores/2/products/4/images/6/prepare','POST');
 vi.mocked(marketplaceRequest).mockResolvedValue({...receipt,imageId:7});
 await expect(preparePublishingImage(2,4,6)).rejects.toThrow('receipt');
 vi.mocked(marketplaceRequest).mockClear();
 await expect(preparePublishingImage(0,4,6)).rejects.toThrow('identity');
 expect(marketplaceRequest).not.toHaveBeenCalled();
});
