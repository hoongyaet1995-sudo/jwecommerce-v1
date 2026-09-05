import {readAdminBootstrap} from './adminBootstrap';

export async function marketplaceRequest<T>(path = '', method = 'GET', body?: unknown): Promise<T> {
  const response = await fetch(`/admin/api/marketplace${path}`, {
    method, credentials:'same-origin',
    headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':readAdminBootstrap().csrfToken},
    ...(body === undefined ? {} : {body:JSON.stringify(body)}),
  });
  if(response.status===204)return undefined as T;
  const payload=await response.json();
  if(!response.ok)throw new Error(payload.errors ? Object.values(payload.errors).flat().join(' ') : payload.message || 'Marketplace request failed.');
  return payload as T;
}
