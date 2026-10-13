export interface ShellDesign {
 business:'commerce'|'service';style:'editorial'|'soft'|'modern';appearance:'light'|'dark';
 showAnnouncement:boolean;showAccount:boolean;showCurrency:boolean;showThemeSwitch:boolean;
 ctaLabel:string;ctaUrl:string;tagline:string;contactEmail:string;
}
export const shellDesignDefaults=(business:ShellDesign['business']='commerce'):ShellDesign=>({business,style:'editorial',appearance:'light',showAnnouncement:false,showAccount:false,showCurrency:false,showThemeSwitch:false,ctaLabel:'',ctaUrl:'',tagline:'',contactEmail:''});
export const resolveShellDesign=(global:ShellDesign|undefined,page?:ShellDesign)=>global??page;
export const shellStyleChoices=[{id:'editorial',name:'Editorial',description:'Photography · Creative studios',paper:'#f7f4ee',ink:'#292724'},{id:'soft',name:'Soft & refined',description:'Beauty · Jewellery',paper:'#faf2ee',ink:'#503b41'},{id:'modern',name:'Clean & modern',description:'Tech · Everyday retail',paper:'#ffffff',ink:'#141820'}] as const;
export function isShellDesign(value:unknown):value is ShellDesign {
 if(!value||typeof value!=='object'||Array.isArray(value))return false;
 const v=value as Record<string,unknown>;
 return ['commerce','service'].includes(String(v.business))&&['editorial','soft','modern'].includes(String(v.style))&&['light','dark'].includes(String(v.appearance))&&['showAnnouncement','showAccount','showCurrency','showThemeSwitch'].every(k=>typeof v[k]==='boolean')&&['ctaLabel','ctaUrl','tagline','contactEmail'].every(k=>typeof v[k]==='string');
}
