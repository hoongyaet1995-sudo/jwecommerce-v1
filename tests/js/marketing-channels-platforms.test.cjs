const {test}=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const source=fs.readFileSync(path.join(__dirname,'../../public/marketing-studio/channels.js'),'utf8');
function page(platform=null,search=''){
 const nodes=new Map(),events={};let requests=0;
 const caps={identity_granted:true,publishing_granted:false,analytics_granted:false};
 const accounts=[{id:'a',platform:'facebook',name:'First Page',account_id:'123',state:'connected',permissions:[],capabilities:caps},{id:'b',platform:'facebook',name:'Second Page',account_id:'456',state:'unverified',permissions:[],capabilities:caps},{id:'c',platform:'instagram',name:'Instagram Brand',account_id:'789',state:'connected',permissions:[],capabilities:caps}];
 const boot={channelsPlatform:platform,csrf:'test-csrf',urls:{channels:'/admin/marketing/channels'},channels:{can_manage:true,configuration:{ready:true},accounts,pending:{facebook:[],instagram:[]}}};
 const get=id=>{if(!nodes.has(id))nodes.set(id,{textContent:id==='navigation-boot'?JSON.stringify(boot):'',innerHTML:'',close(){},showModal(){}});return nodes.get(id);};
 const window={};vm.runInNewContext(source,{window,document:{getElementById:get,addEventListener:(n,fn)=>events[n]=fn},location:{search},URLSearchParams,setTimeout(){},fetch(){requests++;throw new Error('Unexpected provider request');}});window.JWChannels.render();return {nodes,events,get requests(){return requests;},html:get('content').innerHTML};
}
test('overview has four platform entries and no account data, counts or OAuth form',()=>{
 const r=page();assert.equal((r.html.match(/class="studio-card platform-card"/g)||[]).length,4);assert.doesNotMatch(r.html,/First Page|Second Page|Instagram Brand|\d+ connected|<form/);assert.equal(r.requests,0);
});
test('platform details show multiple real records for that platform only',()=>{
 const r=page('facebook');assert.equal(r.nodes.get('title').textContent,'Facebook accounts');assert.match(r.html,/First Page/);assert.match(r.html,/Second Page/);assert.doesNotMatch(r.html,/Instagram Brand/);assert.match(r.nodes.get('page-action').innerHTML,/facebook\/authorize/);assert.equal(r.requests,0);
});
test('external platform details expose connection form without automatic OAuth',()=>{
 const r=page('youtube');assert.doesNotMatch(r.html,/Coming soon|First Page/);assert.match(r.nodes.get('page-action').innerHTML,/youtube\/authorize/);assert.equal(r.requests,0);
});
test('existing Meta callback query opens the right account detail',()=>{
 const r=page(null,'?platform=instagram&result=select');assert.equal(r.nodes.get('title').textContent,'Instagram accounts');assert.match(r.html,/Instagram Brand/);assert.doesNotMatch(r.html,/First Page/);assert.equal(r.requests,0);
});
test('search filters multiple accounts without a provider request',()=>{
 const r=page('facebook');r.events.input({target:{id:'account-search',value:'second'}});
 const html=r.nodes.get('account-rows').innerHTML;assert.match(html,/Second Page/);assert.doesNotMatch(html,/First Page|Instagram Brand/);assert.equal(r.requests,0);
});
test('Manage opens the chosen account status and permissions without checking or disconnecting',()=>{
 const r=page('facebook');r.events.click({target:{closest:()=>({dataset:{channel:'manage',platform:'facebook',id:'b'}})},preventDefault(){},stopImmediatePropagation(){}});
 assert.equal(r.nodes.get('dialog-title').textContent,'Manage Second Page');const html=r.nodes.get('dialog-body').innerHTML;assert.match(html,/Second Page/);assert.match(html,/View granted permissions/);assert.match(html,/Check connection/);assert.doesNotMatch(html,/First Page|Instagram Brand/);assert.equal(r.requests,0);
});
