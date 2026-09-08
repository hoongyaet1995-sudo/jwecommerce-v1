import {it,expect,vi} from 'vitest';
import {warehouseSaver} from './warehouseApi';

it('binds update identity/version and does not send editable actor or stock fields',async()=>{
 const request=vi.fn().mockResolvedValue(new Response(JSON.stringify({id:4,version:3})));
 const result=await warehouseSaver('csrf',{id:'4',version:2},request)({code:'CYB',name:'Cyberjaya',status:'active',isDefaultMaster:true});
 expect(result.success).toBe(true);
 expect(request.mock.calls[0][0]).toBe('/admin/api/inventory/warehouses/4');
 expect(JSON.parse(request.mock.calls[0][1].body)).toEqual({code:'CYB',name:'Cyberjaya',status:'active',is_default_master:true,version:2});
});
it('does not turn a conflict or transport failure into success',async()=>{
 const conflict=vi.fn().mockResolvedValue(new Response(JSON.stringify({errors:{warehouse:['Warehouse changed.']}}),{status:422}));
 expect(await warehouseSaver('csrf',{id:'4',version:2},conflict)({})).toEqual({success:false,error:'Warehouse changed.'});
 const failure=vi.fn().mockRejectedValue(new Error('offline'));
 expect((await warehouseSaver('csrf',null,failure)({})).success).toBe(false);
});
