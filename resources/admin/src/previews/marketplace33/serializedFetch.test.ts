import {describe,expect,it} from 'vitest';
import {serializedFetch} from './serializedFetch';

describe('serializedFetch',()=>{
  it('starts the next request only after the previous request settles',async()=>{
    const started:string[]=[];
    let releaseFirst!:()=>void;
    const first=new Promise<Response>(resolve=>{releaseFirst=()=>resolve(new Response('{}'));});
    const request=(url:RequestInfo|URL)=>{
      started.push(String(url));
      return String(url)==='/first'?first:Promise.resolve(new Response('{}'));
    };
    const one=serializedFetch('/first',undefined,request);
    const two=serializedFetch('/second',undefined,request);
    await Promise.resolve();
    expect(started).toEqual(['/first']);
    releaseFirst();
    await Promise.all([one,two]);
    expect(started).toEqual(['/first','/second']);
  });
});
