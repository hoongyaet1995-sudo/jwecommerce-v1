import React from 'react';
export function PageSectionContainer({children,width='standard',fullBleed=false}:{children:React.ReactNode;width?:'standard'|'wide';fullBleed?:boolean}) {
 return <div data-page-container data-container-width={width} data-container-mode={fullBleed?'full-bleed':'page'} style={{maxWidth:fullBleed?'none':width==='wide'?1440:1280}} className={`@container mx-auto w-full min-w-0 ${fullBleed?'':'px-6 @min-[640px]:px-10 @min-[1024px]:px-16'}`}>{children}</div>;
}
