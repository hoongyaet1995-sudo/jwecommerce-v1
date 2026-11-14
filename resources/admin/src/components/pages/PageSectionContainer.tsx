import React from 'react';
export type PageContainerWidth='standard'|'wide'|'full'|'site';
/** Site is an explicit opt-in; existing authored Standard/Wide pages retain their geometry. */
export function pageContainerStyle(width:PageContainerWidth='standard',fullBleed=false):React.CSSProperties {
 return {maxWidth:fullBleed?'none':width==='site'?'var(--storefront-content-width, 1280px)':width==='full'?'100%':width==='wide'?1440:1280,...(!fullBleed&&width==='site'?{paddingInline:'var(--storefront-content-gutter, 24px)',boxSizing:'border-box' as const}:{})};
}
export function PageSectionContainer({children,width='standard',fullBleed=false,nativeGutter=false}:{children:React.ReactNode;width?:PageContainerWidth;fullBleed?:boolean;nativeGutter?:boolean}) {
 return <div data-page-container data-container-width={width} data-container-mode={fullBleed?'full-bleed':'page'} style={pageContainerStyle(width,fullBleed)} className={`@container mx-auto w-full min-w-0 ${fullBleed||nativeGutter||width==='site'?'':'px-6 @min-[640px]:px-10 @min-[1024px]:px-16'}`}>{children}</div>;
}
