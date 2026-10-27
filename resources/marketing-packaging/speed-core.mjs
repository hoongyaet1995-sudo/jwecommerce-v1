export const overlapSpeech=(scene,plan)=>(!!scene.spoken_text?.trim()&&!scene.words?.length)||(scene.words||[]).some(w=>/[\p{L}\p{N}]/u.test(w.text)&&w.start_frame<plan.end_frame&&w.end_frame>plan.start_frame);
export function mappedFrame(frame,plan){const n=Math.max(0,frame),a=plan.start_frame,b=plan.end_frame,r=plan.rate;return n<=a?n:n<b?a+(n-a)/r:n-(b-a)+(b-a)/r;}
export const plannedFrames=(scene,plan)=>Math.round(mappedFrame(scene.frames,plan));
export const effectiveRate=(frame,plan,blocked)=>!blocked&&frame>=plan.start_frame&&frame<plan.end_frame?plan.rate:1;
