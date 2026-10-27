import React,{useRef} from 'react';
export default function TimingRange({card,cards,frames,onChange}){
 const track=useRef(null);
 const others=cards.filter(c=>c.id!==card.id&&c.scene_number===card.scene_number);
 const lower=Math.max(0,...others.filter(c=>c.end_frame<=card.start_frame).map(c=>c.end_frame));
 const upper=Math.min(frames,...others.filter(c=>c.start_frame>=card.end_frame).map(c=>c.start_frame));
 const bounds=side=>side==='start_frame'?[lower,card.end_frame-12]:[card.start_frame+12,upper];
 const change=(side,value)=>{const [min,max]=bounds(side);if(min<=max)onChange(side,Math.max(min,Math.min(max,Math.round(value))));};
 const pointer=(e,side)=>{const rect=track.current.getBoundingClientRect();change(side,(e.clientX-rect.left)/rect.width*frames);};
 return <><div className="info-range speed-range" ref={track} role="group" aria-label="拖动调整变速区间"><div className="info-range-rail"/><div className="info-range-fill" style={{left:`${card.start_frame/frames*100}%`,width:`${(card.end_frame-card.start_frame)/frames*100}%`}}/>{[['start_frame','开始'],['end_frame','结束']].map(([side,label])=><button key={side} type="button" className="info-range-handle" role="slider" aria-label={`拖动${label}时间`} aria-valuemin={bounds(side)[0]} aria-valuemax={bounds(side)[1]} aria-valuenow={card[side]} aria-valuetext={`${(card[side]/24).toFixed(2)}秒`} style={{left:`${card[side]/frames*100}%`}} onPointerDown={e=>{e.preventDefault();e.currentTarget.setPointerCapture(e.pointerId);pointer(e,side);}} onPointerMove={e=>{if(e.currentTarget.hasPointerCapture(e.pointerId))pointer(e,side);}} onPointerUp={e=>{if(e.currentTarget.hasPointerCapture(e.pointerId))e.currentTarget.releasePointerCapture(e.pointerId);}} onKeyDown={e=>{const delta={ArrowLeft:-1,ArrowDown:-1,ArrowRight:1,ArrowUp:1,PageDown:-24,PageUp:24}[e.key];if(delta!==undefined){e.preventDefault();change(side,card[side]+delta);}else if(e.key==='Home'||e.key==='End'){e.preventDefault();change(side,bounds(side)[e.key==='Home'?0:1]);}}}><span/></button>)}</div><div className="info-range-help">拖左端调整开始，拖右端调整结束 · 最短0.50秒</div></>;
}
