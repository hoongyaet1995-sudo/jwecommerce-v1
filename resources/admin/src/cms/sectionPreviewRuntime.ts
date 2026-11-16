import type {PageSection,PageDocument} from '../types';
import type {PageRuntimePayload,PageRuntimeSectionFacts} from './PageRuntimeContext';
import {demoPalette,demoTypeScale,demoStyle} from './sectionDefaultDesign';
import {resolvePlanComparison,isPlanComparison} from './planComparison';
const image='/images/cms/defaults/neutral/photo-1000-center.svg';
/** Isolated render fixtures only; no facts are installed in merchant data. */
export function sectionPreviewPayload(section:PageSection,unified:boolean):PageRuntimePayload{
 const palette=section.brandPalette??demoPalette;
 const products=Array.from({length:4},(_,i)=>({id:`demo-${i}`,title:'Lorem ipsum',slug:'#sample',brand:'Demo',summary:'Lorem ipsum.',images:[image],tags:[],basePrice:99,baseCompareAtPrice:null,purchaseMode:'display' as const}));
 const facts:PageRuntimeSectionFacts={promotions:{offers:[0,1].map(i=>({id:'sample-offer-'+i,name:'Lorem ipsum',description:'Lorem ipsum dolor sit amet.',code:'DEMO',benefit:'Demo offer',minimumSpend:null,endsAt:null,status:'active' as const}))},listing:{sourceId:{valid:true},source:null,products},productIds:{valid:true,products,invalidReferenceIds:[]},tabs:Array.from({length:12},()=>({sourceId:{valid:true},source:null,products})),payments:{methods:[{id:'sample',title:'Lorem ipsum',description:'Demo payment method',category:'card',status:'available'}],bankTransfer:false},delivery:{rows:[{id:'demo',title:'Lorem ipsum',deliveryEstimate:'Lorem ipsum',deliveryFee:'RM0'}]},starterPricing:{status:'ready',kind:'addon',name:'Lorem ipsum',label:'Lorem ipsum',priceLabel:'RM99'}};
 if(isPlanComparison(section))facts.planComparison=resolvePlanComparison({...section,planContentSource:'manual'});
 const document={title:'Demo section',slug:'sample',description:'',template:'landing',sections:[section],containerWidth:'standard',...(unified?{style:{...demoStyle,palette,typography:{...demoTypeScale,number:section.typography?.number??demoTypeScale.number}}}:{})} as PageDocument;
 return {page:{id:'sample-only',status:'draft',stage:'draft',schemaVersion:1,lockVersion:1,contentHash:'sample',document},catalog:{products,categories:[],collections:[]},forms:{contact:{},newsletter:{}},links:{},runtime:{sections:{0:facts},errors:{}},context:{mode:'preview',currency:'MYR',...(unified?{themePalette:palette}:{})}};
}
