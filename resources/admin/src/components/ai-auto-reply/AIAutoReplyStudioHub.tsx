import React from 'react';
import {useAdmin} from '../../context/AdminContext';
import {AIAutoReplyNavigator} from './AIAutoReplyNavigator';
import {AIPersonalityTab} from './AIPersonalityTab';
import {AIKnowledgeScopeTab} from './AIKnowledgeScopeTab';
import {AIGuardrailsTab} from './AIGuardrailsTab';
import {ArrowLeft,Save,RotateCcw} from 'lucide-react';
export const AIAutoReplyStudioHub:React.FC=()=>{
 const c=useAdmin();const titles:Record<string,string>={'ai-personality':'AI Personality & Tone','ai-knowledge':'AI Knowledge Scope','ai-guardrails':'AI Safety Guardrails'};const title=titles[c.currentSection];
 if(!title)return <AIAutoReplyNavigator/>;
 return <div className="space-y-6 max-w-7xl mx-auto pb-16"><button onClick={()=>c.setCurrentSection('ai-auto-reply')} className="flex gap-2 items-center text-xs text-neutral-500 hover:text-teal-600"><ArrowLeft className="w-4 h-4"/>Back to AI Auto Reply</button><div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-6 flex flex-wrap justify-between items-center gap-4"><h1 className="text-xl font-bold">{title}</h1><div className="flex gap-3"><button onClick={c.resetAIAutoReplyToDefaults} className="px-3 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 flex gap-2 items-center"><RotateCcw className="w-4 h-4"/>Reset Defaults</button><button onClick={()=>c.saveAIAutoReplyConfig(c.aiAutoReplyConfig)} className="px-4 py-2 text-xs font-semibold rounded-xl bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 flex gap-2 items-center"><Save className="w-4 h-4"/>Save Configuration</button></div></div>{c.currentSection==='ai-personality'?<AIPersonalityTab/>:c.currentSection==='ai-knowledge'?<AIKnowledgeScopeTab/>:<AIGuardrailsTab/>}</div>;
};
