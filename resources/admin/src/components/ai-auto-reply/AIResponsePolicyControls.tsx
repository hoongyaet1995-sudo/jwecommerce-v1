import React from 'react';
import {AISettingToggle} from './AISettingInputs';
export type AIResponsePolicy={partialAnswers:boolean;clarificationEnabled:boolean};
export function AIResponsePolicyControls({value,onChange}:{value:AIResponsePolicy;onChange:(value:AIResponsePolicy)=>void}){
 return <div className="space-y-3 mt-4 pt-4 border-t border-neutral-200 dark:border-neutral-800">
 <h3 className="text-xs font-semibold">Answer Handling</h3>
 <label className="flex items-center gap-3 text-xs"><AISettingToggle aria-label="Keep confirmed partial answers" checked={value.partialAnswers} onChange={e=>onChange({...value,partialAnswers:e.target.checked})}/>Keep confirmed partial answers when another check has no result or fails</label>
 <label className="flex items-center gap-3 text-xs"><AISettingToggle aria-label="Ask for missing context" checked={value.clarificationEnabled} onChange={e=>onChange({...value,clarificationEnabled:e.target.checked})}/>Ask one useful question when essential context is missing</label>
 <p className="text-xs text-neutral-500">Identity verification and source permissions are always required. Save Configuration to apply changes.</p>
 </div>;
}
