type QuestionState={token:string;questionToken?:string;brief:{questions:unknown[]};activity?:{id:string;action:string}[]};
type SavedTask={status?:string;response?:{data?:{token?:string;brief?:{questions?:unknown[]}}}};
/** Generation approval can become stale without invalidating the original questions. */
export async function questionSessionToken(state:QuestionState,load:(id:string)=>Promise<SavedTask>):Promise<string>{
 if(state.questionToken||state.token)return state.questionToken||state.token;
 const operation=[...(state.activity??[])].reverse().find(item=>['brief','answer'].includes(item.action));
 if(operation){
  const task=await load(operation.id),data=task.response?.data;
  if(task.status==='completed'&&data?.token&&JSON.stringify(data.brief?.questions)===JSON.stringify(state.brief.questions))return data.token;
 }
 throw new Error('Your answers are kept. Close this dialog and update the brief before continuing.');
}
