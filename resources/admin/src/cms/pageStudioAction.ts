export function pageStudioAction(step:number,state:{token:string;questions:number;images:number}){
 if(step===0)return 'start';if(step===1)return 'prepare';
 if(step===2)return !state.token?'update':state.questions?'questions':'copy';
 if(step===3)return state.images?'images':'review';return 'save';
}
