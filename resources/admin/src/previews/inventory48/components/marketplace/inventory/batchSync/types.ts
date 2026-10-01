export type InventorySelectionItem={variantId:number;masterSku:string;productName:string};
export type DisplaySnapshot={sku:string;name:string;image:string;store:string;facility:string};
export type ReviewTarget={schemaVersion:1;rowKey:string;variantId:number;platform:string;storeId:number|null;facilityId:number|null;mode:'shared_pool'|'independent_stores';configurationVersion:number;balanceId:number|null;balanceVersion:number|null;quantity:number|null;listingId:number|null;providerShopId:string|null;externalProductId:string|null;externalVariantId:string|null;providerLocationId:string|null;topologyFingerprint:string;policyFingerprint:string;providerAddressKey:string|null;disposition:'valid'|'skipped';reasonCode:string|null;display:DisplaySnapshot};
export type Review={id:string;actorId:number;kind:'initial'|'retry';batchId:string|null;variantIds:number[];itemIds:string[];mode:'shared_pool'|'independent_stores';configurationVersion:number;targets:ReviewTarget[];digest:string;expiresAt:string;counts:{skus:number;validDestinations:number;skippedRows:number}};
export type SubmissionReceipt={batch_id:string;generation:number;status_url:string;replayed:boolean};
export type SubmissionLookup=SubmissionReceipt|{status:'not_found_yet'};
export type Attempt={id:string;generation:number;task_id:string|null;snapshot_token:string|null;target_quantity:number|null;observed_quantity:number|null;phase:string;checked_at:string|null;check_count:number;exhausted_at:string|null;diagnostics:Record<string,unknown>};
export type BatchItem={id:string;row_key:string;display:DisplaySnapshot;current_attempt:Attempt|null;history:Attempt[];status:'queued'|'confirming'|'confirmed'|'failed'|'skipped';reason_code:string|null;message:string|null;can_retry:boolean;can_check:boolean};
export type BatchAggregate='running'|'needs_attention'|'completed'|'completed_with_issues';
export type DestinationCounts={queued:number;confirming:number;confirmed:number;failed:number;skipped:number};
// T9 still owns additional SKU/selection/actor summary members. These stay opaque until its projection lands.
export type BatchDetail={id:string;mode:'shared_pool'|'independent_stores';revision:number;created_at:string;actor:Record<string,unknown>;selection:Record<string,unknown>;aggregate:BatchAggregate;counts:{skus:number;destinations:DestinationCounts;[key:string]:unknown};items:BatchItem[];next_poll_ms:number|null};
export type BatchPage={data:BatchDetail[];next_cursor:string|null;active_count:number};
export type CheckInput={task_id:string;operation_key:string;snapshot_token:string};
export type FrozenScope={kind:'initial';variantIds:number[]}|{kind:'retry';batchId:string;itemIds:string[]};
export type BatchSyncApi={createReview(variantIds:number[],reviewRequestKey:string):Promise<Review>;confirmReview(reviewId:string,operationKey:string):Promise<SubmissionReceipt>;resolveSubmission(operationKey:string):Promise<SubmissionLookup>;getBatch(batchId:string):Promise<BatchDetail>;listBatches(cursor?:string,activeOnly?:boolean):Promise<BatchPage>;createRetryReview(batchId:string,itemIds:string[],reviewRequestKey:string):Promise<Review>;confirmRetryReview(reviewId:string,operationKey:string):Promise<SubmissionReceipt>;checkConfirmation(batchId:string,itemId:string,input:CheckInput):Promise<BatchItem>};
export type BatchSyncState=
 | {kind:'idle';generation:number}
 | {kind:'reviewing';generation:number;frozenVariantIds:number[];reviewRequestKey:string}
 | {kind:'review';generation:number;review:Review;frozenVariantIds:number[];reviewRequestKey:string}
 | {kind:'submitting'|'unknown';generation:number;review?:Review;reviewId:string;operationKey:string;retry:boolean;scope:FrozenScope;message?:string;code?:string;status?:number;batchReference?:{batchId:string;generation:number;statusUrl:string}}
 | {kind:'batch';generation:number;batchId:string;batch:BatchDetail}
 | {kind:'error';generation:number;message:string;code?:string;status?:number;scope?:FrozenScope;changedRows?:unknown[];changedRowsTotal?:number;changedRowsTruncated?:boolean;batchReference?:{batchId:string;generation:number;statusUrl:string}};
export type BatchSyncController={state:BatchSyncState;activity:BatchPage|null;openReview(items:InventorySelectionItem[],selectedVariantIds:number[]):Promise<void>;submit():Promise<void>;openBatch(batchId:string):Promise<void>;close():void;reviewFailed(itemIds?:string[]):Promise<void>;reviewAgain():Promise<void>;check(itemId:string,input:CheckInput):Promise<void>;recover():Promise<void>};
