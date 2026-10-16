export async function enquiryRequest<T>(url:string,method='GET',data?:unknown,signal?:AbortSignal):Promise<T>{
 const response=await fetch(url,{method,signal,credentials:'same-origin',headers:{Accept:'application/json',...(data?{'Content-Type':'application/json','X-CSRF-TOKEN':window.__JW_ADMIN__?.csrfToken??''}:{})},...(data?{body:JSON.stringify(data)}:{})});
 const body=await response.json().catch(()=>null);
 if(!response.ok)throw new Error(body?.errors?Object.values(body.errors).flat().join(' '):body?.message??'Unable to load enquiries. Please try again.');
 return body;
}
