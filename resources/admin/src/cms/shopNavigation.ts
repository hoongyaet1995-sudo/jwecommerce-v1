import type {PageSection,SectionItem} from '../types';
import type {PageRuntimeSource} from './PageRuntimeContext';
export function shopNavigationHref(value:string):string {
 if (/^[\u0000-\u0020]|[\u0000-\u001f\\]/.test(value)) return '';
 if (/^\/(?!\/)|^#[A-Za-z][\w-]*$/.test(value)) return value;
 try {const url=new URL(value);return url.protocol==='https:'?value:'';} catch {return '';}
}

/** Banner frames own geometry; retain content, destinations and non-geometric image overrides. */
export function changeShopNavigationLayout(section:PageSection,preset:NonNullable<PageSection['shopNavigationPreset']>):PageSection {
 return {...section,shopNavigationPreset:preset,...(preset==='collection-banners'&&section.imageSlots?{imageSlots:section.imageSlots.map(({imageRatio,imageWidth,imageShape,...slot})=>slot)}:{})};
}

/** Taxonomy owns destinations; optional authored labels/photos affect presentation only. */
export function resolveShopCard(item:SectionItem,catalog:{categories:PageRuntimeSource[];collections:PageRuntimeSource[]}) {
 const kind=item.sourceType??'custom';
 const source=kind==='category'?catalog.categories.find(s=>s.id===item.sourceId):kind==='collection'?catalog.collections.find(s=>s.id===item.sourceId):undefined;
 return {title:item.title||source?.label||'Choose a destination',image:item.imageUrl||source?.image||'',
  href:kind==='custom'?shopNavigationHref(item.linkUrl??''):source?`/${kind==='category'?'categories':'collections'}/${encodeURIComponent(source.slug)}`:'',
  available:kind==='custom'?Boolean(shopNavigationHref(item.linkUrl??'')):Boolean(source)};
}
