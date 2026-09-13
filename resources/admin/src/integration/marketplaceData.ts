import {readAdminBootstrap} from './adminBootstrap';

export class MarketplaceRequestError extends Error {
  readonly code?: string;
  readonly taskId?: number;
  readonly retryAllowed?: boolean;
  constructor(readonly status: number, payload?: Record<string,unknown>) {
    const errors=payload?.errors;
    const messages=errors&&typeof errors==='object'&&!Array.isArray(errors)
      ?Object.values(errors).flat().filter((value):value is string=>typeof value==='string'):[];
    super(messages.join(' ')||(typeof payload?.message==='string'?payload.message:'Marketplace request failed. Check the saved operation status before retrying.'));
    this.name='MarketplaceRequestError';
    this.code=typeof payload?.code==='string'?payload.code:undefined;
    this.taskId=typeof payload?.taskId==='number'&&Number.isSafeInteger(payload.taskId)&&payload.taskId>0?payload.taskId:undefined;
    this.retryAllowed=typeof payload?.retryAllowed==='boolean'?payload.retryAllowed:undefined;
  }
}

export async function marketplaceRequest<T>(path = '', method = 'GET', body?: unknown): Promise<T> {
  const response = await fetch(`/admin/api/marketplace${path}`, {
    method, credentials:'same-origin',
    headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':readAdminBootstrap().csrfToken},
    ...(body === undefined ? {} : {body:JSON.stringify(body)}),
  });
  if(response.status===204)return undefined as T;
  let payload: unknown;
  try {payload=await response.json();}
  catch {throw new MarketplaceRequestError(response.status);}
  if(!response.ok)throw new MarketplaceRequestError(response.status,payload&&typeof payload==='object'&&!Array.isArray(payload)?payload as Record<string,unknown>:undefined);
  return payload as T;
}
