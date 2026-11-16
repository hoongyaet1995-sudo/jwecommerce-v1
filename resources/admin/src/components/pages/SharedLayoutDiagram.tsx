import React from 'react';
const known=new Set(['hero-centered','hero-split','cards-grid','cards-bento','cards-compact','story-rows','story-split','process-cards','process-orbit','metrics-bento','metrics-row','comparison-narrative','preview-tabs','sticky-stack','logo-marquee','faq-list','closing-cta','pricing-cards','pricing-matrix']);
export function hasSharedDiagram(value:string){return known.has(value)}
export function SharedLayoutDiagram({value}:{value:string}){
 const rect=(x:number,y:number,w:number,h:number,o=1)=><rect key={`${x}-${y}`} x={x} y={y} width={w} height={h} rx="2" opacity={o}/>;
 let shapes:React.ReactNode;
 if(value==='hero-centered')shapes=<>{rect(22,7,56,7)}{rect(31,21,38,3)}{rect(37,31,26,7)}{rect(7,45,50,17,.35)}{rect(63,45,30,17,.2)}</>;
 else if(value==='hero-split'||value==='story-split')shapes=<>{rect(7,18,37,6)}{rect(7,31,32,3)}{rect(7,44,24,7)}{rect(54,7,39,54,.35)}</>;
 else if(value==='cards-bento'||value==='metrics-bento')shapes=<>{rect(6,8,55,51,.35)}{rect(66,8,28,23,.2)}{rect(66,36,28,23,.4)}{value==='metrics-bento'&&<text x="20" y="40" fontSize="20">3</text>}</>;
 else if(value==='cards-grid'||value==='process-cards'||value==='pricing-cards')shapes=[7,37,67].map((x,i)=><g key={x}>{rect(x,8,25,52,.25)}{value==='process-cards'?<text x={x+8} y="26" fontSize="12">{i+1}</text>:rect(x+4,17,17,6)}{rect(x+4,34,17,3)}{rect(x+4,44,17,2)}{value==='pricing-cards'&&rect(x+4,52,17,5)}</g>);
 else if(value==='story-rows')shapes=[5,26,47].map((y,i)=><g key={y}>{rect(i===1?57:6,y,37,17,.3)}{rect(i===1?6:57,y+3,30,4)}{rect(i===1?6:57,y+11,25,2,.5)}</g>);
 else if(value==='process-orbit')shapes=<><circle cx="50" cy="34" r="23" fill="none" stroke="currentColor"/><circle cx="50" cy="34" r="7"/>{[[50,9],[76,34],[50,59],[24,34]].map(([x,y])=><circle key={x+'-'+y} cx={x} cy={y} r="6"/>)}</>;
 else if(value==='metrics-row')shapes=[8,38,68].map(x=><g key={x}><text x={x+5} y="34" fontSize="20">4</text>{rect(x,45,24,3,.5)}</g>);
 else if(value==='preview-tabs')shapes=<>{[8,26,44].map(y=>rect(6,y,33,12,y===8?.5:.2))}{rect(45,8,49,49,.35)}</>;
 else if(value==='sticky-stack')shapes=[0,1,2].map(i=>rect(8+i*5,6+i*10,74,33,.2+i*.2));
 else if(value==='logo-marquee')shapes=<>{[9,31,53,75].map(x=>rect(x,24,16,18,.5))}<path d="M12 53h72l-5-4m5 4-5 4" fill="none" stroke="currentColor"/></>;
 else if(value==='comparison-narrative')shapes=<>{rect(7,10,39,48,.2)}{rect(54,10,39,48,.4)}{[23,34,45].map(y=><g key={y}>{rect(13,y,27,3)}{rect(60,y,27,3)}</g>)}</>;
 else if(value==='pricing-matrix')shapes=<>{rect(6,8,88,10,.4)}{[22,33,44,55].map(y=>rect(6,y,88,6,.2))}{[40,57,74,92].map(x=><path key={x} d={`M${x} 8v53`} stroke="currentColor" opacity=".4"/>)}</>;
 else if(value==='closing-cta')shapes=<>{rect(5,9,90,50,.2)}{rect(21,21,58,6)}{rect(31,37,38,10)}</>;
 else shapes=[9,26,43].map(y=><g key={y}>{rect(8,y,84,12,.2)}{rect(14,y+4,49,3)}{value==='faq-list'?<text x="80" y={y+9} fontSize="10">+</text>:<circle cx="78" cy={y+6} r="3"/>}</g>);
 return <svg aria-hidden="true" viewBox="0 0 100 68" className="w-full h-16 rounded-lg bg-neutral-100 dark:bg-neutral-800 text-indigo-500 dark:text-indigo-400 fill-current">{shapes}</svg>;
}
