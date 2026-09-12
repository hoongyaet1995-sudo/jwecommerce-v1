let pending:Promise<void>=Promise.resolve();

export function serializedFetch(input:RequestInfo|URL,init?:RequestInit,request:typeof fetch=fetch):Promise<Response> {
  const current=pending.then(()=>request(input,init));
  pending=current.then(()=>undefined,()=>undefined);
  return current;
}
