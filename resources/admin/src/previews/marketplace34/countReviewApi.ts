export interface CountReview {
  id: string;
  physical_count: number;
  physical_before: number | null;
  actor_id: number;
  atp: number | null;
}

export function countReviewApi(balanceId: number, csrf: string, request: typeof fetch = fetch) {
  async function post(path: string, payload: object) {
    if (!Number.isSafeInteger(balanceId) || balanceId < 1 || !csrf) throw new Error('Reload the inventory page before reviewing.');
    const response = await request(path, {method: 'POST', credentials: 'same-origin',
      headers: {'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf},
      body: JSON.stringify(payload)});
    const data = await response.json().catch(() => null);
    if (!response.ok) throw new Error(data?.errors?.inventory?.[0] || data?.message || 'Inventory request failed. Reload and review again.');
    if (!data || typeof data !== 'object') throw new Error('Inventory response was incomplete. Reload before continuing.');
    return data;
  }
  return {
    async adjustment(delta: number, reason: string): Promise<CountReview> {
      const data=await post('/admin/api/inventory/adjustment-reviews',{balance_id:balanceId,quantity_delta:delta,reason});
      if(typeof data.id!=='string'||!/^[0-9a-f-]{36}$/i.test(data.id)||data.operation_kind!=='delta'||data.quantity_delta!==delta
        ||!Number.isSafeInteger(data.physical_before)||!Number.isSafeInteger(data.physical_count)||data.physical_count<0
        ||data.physical_count!==data.physical_before+delta||!Number.isSafeInteger(data.actor_id)||data.actor_id<1
        ||!(data.atp===null||(Number.isSafeInteger(data.atp)&&data.atp>=0)))
        throw new Error('Inventory adjustment review was incomplete. Reload before continuing.');
      return data;
    },
    async review(count: number, reason: string): Promise<CountReview> {
      const data = await post('/admin/api/inventory/count-reviews', {balance_id: balanceId, physical_count: count, reason});
      if (typeof data.id !== 'string' || !/^[0-9a-f-]{36}$/i.test(data.id) || data.physical_count !== count
        || !Number.isSafeInteger(data.actor_id) || data.actor_id < 1 || !(data.atp===null||(Number.isSafeInteger(data.atp)&&data.atp>=0))
        || !(data.physical_before === null || (Number.isSafeInteger(data.physical_before) && data.physical_before >= 0))) {
        throw new Error('Inventory review was incomplete. Reload before continuing.');
      }
      return data;
    },
    async confirm(review: CountReview): Promise<void> {
      const data = await post(`/admin/api/inventory/count-reviews/${encodeURIComponent(review.id)}/confirm`, {acknowledged: true});
      if (data.review_id !== review.id || data.physical_after !== review.physical_count || data.actor_id !== review.actor_id) {
        throw new Error('Confirmation could not be verified. Retry this review before making another adjustment.');
      }
    },
  };
}
