import React from 'react';
import type {PageSection} from '../../types';
import {onColor,type BrandPalette} from '../../cms/pageStyle';
import type {ConnectedSection} from '../../cms/connectedFamilies';
import {connectedPassport} from '../../cms/connectedFamilies';
import {ConnectedNativeComposition} from './ConnectedNativeComposition';
/** The same canonical fields and reviewed native visuals render in Builder and public pages. */
export function ConnectedFamilySection({section,editing=false,authoredSection,palette,links={}}:{section:PageSection;editing?:boolean;authoredSection?:PageSection;palette?:BrandPalette;links?:Record<string,string>}){
 const s=section as ConnectedSection;const p=palette??s.brandPalette;const metadata=connectedPassport(s);if(!metadata)return null;
 const own=authoredSection??section;const explicitSurface=own.surface!==undefined||own.colorOverrides?.includes('customBgColor');
 const surface=explicitSurface?s.customColors?.customBgColor:undefined;
 const nativeContext={default:'',commerce:'product-page ecommerce-page',operations:'service-page service-page-omnichannel',workflow:'service-page service-page-agent-commission',fulfilment:'service-page service-page-wms',website:'service-page service-page-shopping-app'}[s.connectedVisualContext??'default'];
 const contrast=p?.contrast??p?.ink??'#0d1b18';
 const tokens={containerType:'inline-size',containerName:'cms-connected','--cms-primary':p?.accent??s.customColors?.primaryBtnBg??'#ffa1ed','--cms-secondary':p?.secondary??s.customColors?.secondaryBtnBg??'#94ff55','--cms-contrast':contrast,'--cms-on-contrast':onColor(contrast),'--ink':p?.ink??'#0a0b0b','--muted':p?.muted??'#686d6b','--paper':p?.paper??'#ffffff','--connected-padding':s.paddingY?{compact:'48px',normal:'96px',spacious:'160px'}[s.paddingY]:undefined,'--connected-alignment':s.textAlignment} as React.CSSProperties;
 if(explicitSurface)Object.assign(tokens,{'--ink':s.customColors?.titleColor??p?.ink??'#0a0b0b','--muted':s.customColors?.contentColor??p?.muted??'#686d6b','--paper':surface??p?.paper??'#ffffff','--connected-surface':surface});
 const destination=(url?:string)=>url===undefined?undefined:(links[url]??url);
 const linked=Object.keys(links).length?{...s,buttonLink:destination(s.buttonLink),secondaryButtonLink:destination(s.secondaryButtonLink),items:s.items?.map(item=>({...item,linkUrl:destination(item.linkUrl)}))}:s;
 return <div className={`cms-connected-native ${nativeContext}`} data-connected-layout={s.connectedLayout} data-padding={s.paddingY} data-text-alignment={s.textAlignment} data-explicit-surface={surface?'':undefined} data-family={metadata.family} style={tokens}><ConnectedNativeComposition section={linked} editing={editing}/></div>;
}
