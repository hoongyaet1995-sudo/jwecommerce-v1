(function(root){'use strict';root.FreePostFlow={
 setCompanyBadge(doc,name){const label=doc.querySelector('.crm-client-badge-name');if(label)label.textContent=name;},
 action(r){if(r.status==='completed')return 'review';if(r.can_save)return 'save';if(/_requesting$/.test(r.status))return 'wait';if(r.can_step)return 'step';return 'attention';},
 rememberProduct(storage,id){storage.setItem('jw-marketing-free-product',String(id));},
 selectedProduct(storage,products){const id=Number(storage.getItem('jw-marketing-free-product'));return products.find(p=>p.id===id)?.id||products[0]?.id;},
 rememberProducts(storage,ids){storage.setItem('jw-marketing-free-products',JSON.stringify(ids));},
 selectedProducts(storage,products){let ids;try{ids=JSON.parse(storage.getItem('jw-marketing-free-products'));}catch{}if(Array.isArray(ids))return [...new Set(ids.map(Number))].filter(Number.isSafeInteger);const id=Number(storage.getItem('jw-marketing-free-product'));return Number.isSafeInteger(id)&&id>0?[id]:[];},
 newBrief(storage){storage.removeItem('jw-marketing-free-generation-request');}
};})(typeof window==='undefined'?globalThis:window);
