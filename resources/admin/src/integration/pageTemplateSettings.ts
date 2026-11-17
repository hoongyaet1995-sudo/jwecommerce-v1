import type {LaravelAdminBootstrap} from '../types';
import type {PageTemplateContext} from '../cms/pageTemplates';
import {loadShopBranding} from './shopBrandingData';
import {fetchCommerceSettings} from './commerceSettingsData';
import {currencyFor} from './adminMoney';
import {SettingsReadError} from './settingsReadError';
export type TemplateSettingsResult={context:PageTemplateContext;error?:never}|{error:string;context?:never};
/** Both existing persisted sources must succeed; never create from demonstration defaults. */
export async function loadPageTemplateSettings(bootstrap:LaravelAdminBootstrap):Promise<TemplateSettingsResult>{
 const [profile,commerce]=await Promise.allSettled([loadShopBranding(),fetchCommerceSettings(bootstrap)]);
 const failures=[profile,commerce].flatMap((result,index)=>result.status==='rejected'?[result.reason instanceof SettingsReadError?result.reason.message:`${index===0?'Shop profile':'Commerce settings'} could not load. Please retry.`]:[]);
 if(failures.length)return {error:failures.join(' ')};
 if(profile.status!=='fulfilled'||commerce.status!=='fulfilled')return {error:'Saved store settings could not load. Please retry.'};
 return {context:{shopName:profile.value.shopName,tagline:profile.value.tagline,currency:currencyFor(commerce.value.currencies)?.code||profile.value.currency,shippingRates:commerce.value.shippingRates}};
}
