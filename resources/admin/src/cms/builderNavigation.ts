import type {CmsPage} from '../types';
/** Same ownership rules as CmsNavigationLinks: keep current anchors, prefer homepage, never guess ambiguity. Builder is draft-preview context. */
export function builderNavigationHref(url:string,current?:Pick<CmsPage,'id'|'sections'>,pages: CmsPage[]=[]):string {
 const active=pages.filter(p=>p.status!=='archived');const path=url.match(/^\/pages\/([A-Za-z0-9][A-Za-z0-9_-]*)$/);
 if(path){const owners=active.filter(p=>p.slug===path[1]);return owners.length===1?`/admin/pages/${owners[0].id}/preview`:url;}
 const match=url.match(/^#section-([A-Za-z0-9][A-Za-z0-9_-]*)$/);if(!match)return url;
 if(current?.sections.some(s=>s.id===match[1]&&s.isVisible))return url;
 const owners=active.filter(p=>p.sections.some(s=>s.id===match[1]&&s.isVisible)),owner=owners.find(p=>p.isHomepage)??(owners.length===1?owners[0]:undefined);
 return owner?`/admin/pages/${owner.id}/preview${url}`:url;
}
