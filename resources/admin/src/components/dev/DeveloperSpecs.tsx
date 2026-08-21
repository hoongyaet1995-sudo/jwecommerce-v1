import React, { useState } from 'react';
import { ADMIN_ROUTES_SPEC, PHASE2_JSON_SCHEMAS, MASTER_CODE_PROMPT } from '../../data/specData';
import {
  Code,
  FileCode,
  Copy,
  Check,
  Download,
  Terminal,
  Layers,
  Sparkles,
  BookOpen,
  Cpu
} from 'lucide-react';
import { useAdmin } from '../../context/AdminContext';

export const DeveloperSpecs: React.FC = () => {
  const { addToast } = useAdmin();
  const [activeTab, setActiveTab] = useState<'masterPrompt' | 'routes' | 'schemas'>('masterPrompt');
  const [copiedKey, setCopiedKey] = useState<string | null>(null);

  const copyToClipboard = (text: string, key: string) => {
    navigator.clipboard.writeText(text);
    setCopiedKey(key);
    addToast('success', 'Copied to clipboard!');
    setTimeout(() => setCopiedKey(null), 2500);
  };

  return (
    <div className="space-y-6 pb-12">
      {/* Header Bar */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-5 rounded-3xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-sm">
        <div>
          <div className="flex items-center gap-2">
            <span className="px-2.5 py-0.5 text-[10px] font-bold uppercase rounded-full bg-amber-500/20 text-amber-700 dark:text-amber-300 border border-amber-500/30">
              Developer Architecture
            </span>
            <span className="text-xs text-neutral-500">Universal Ecommerce Schema & Master Prompt</span>
          </div>
          <h2 className="text-lg sm:text-xl font-bold text-neutral-900 dark:text-white tracking-tight mt-1">
            Developer Specifications & Master Code Prompt
          </h2>
          <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
            Access the complete data contract, route definitions, JSON validation blueprints, and the complete Master Code Prompt.
          </p>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex items-center gap-2 border-b border-neutral-200 dark:border-neutral-800 pb-2">
        <button
          onClick={() => setActiveTab('masterPrompt')}
          className={`px-4 py-2 text-xs font-bold rounded-xl transition-all flex items-center gap-1.5 ${
            activeTab === 'masterPrompt'
              ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/20'
              : 'bg-white dark:bg-neutral-900 text-neutral-600 dark:text-neutral-400 border border-neutral-200 dark:border-neutral-800'
          }`}
        >
          <Sparkles className="w-3.5 h-3.5" />
          <span>Master Code Prompt</span>
        </button>

        <button
          onClick={() => setActiveTab('routes')}
          className={`px-4 py-2 text-xs font-bold rounded-xl transition-all flex items-center gap-1.5 ${
            activeTab === 'routes'
              ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/20'
              : 'bg-white dark:bg-neutral-900 text-neutral-600 dark:text-neutral-400 border border-neutral-200 dark:border-neutral-800'
          }`}
        >
          <Layers className="w-3.5 h-3.5" />
          <span>Route & Component Specs</span>
        </button>

        <button
          onClick={() => setActiveTab('schemas')}
          className={`px-4 py-2 text-xs font-bold rounded-xl transition-all flex items-center gap-1.5 ${
            activeTab === 'schemas'
              ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/20'
              : 'bg-white dark:bg-neutral-900 text-neutral-600 dark:text-neutral-400 border border-neutral-200 dark:border-neutral-800'
          }`}
        >
          <FileCode className="w-3.5 h-3.5" />
          <span>JSON Schema Blueprints</span>
        </button>
      </div>

      {/* TAB 1: MASTER CODE PROMPT */}
      {activeTab === 'masterPrompt' && (
        <div className="space-y-4 animate-in fade-in">
          <div className="p-4 rounded-2xl bg-indigo-50/60 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800/60 flex items-center justify-between">
            <div className="flex items-center gap-2 text-xs text-indigo-900 dark:text-indigo-300">
              <Cpu className="w-4 h-4 text-indigo-500" />
              <span>Full prompt specification for engineering and LLM code generation.</span>
            </div>

            <button
              onClick={() => copyToClipboard(MASTER_CODE_PROMPT, 'master-prompt')}
              className="px-3.5 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold flex items-center gap-1.5 shadow-md shadow-indigo-500/20"
            >
              {copiedKey === 'master-prompt' ? <Check className="w-3.5 h-3.5" /> : <Copy className="w-3.5 h-3.5" />}
              <span>{copiedKey === 'master-prompt' ? 'Copied!' : 'Copy Master Prompt'}</span>
            </button>
          </div>

          <div className="rounded-3xl border border-neutral-200 dark:border-neutral-800 bg-neutral-950 p-5 text-neutral-300 font-mono text-xs overflow-x-auto shadow-2xl leading-relaxed whitespace-pre-wrap">
            {MASTER_CODE_PROMPT}
          </div>
        </div>
      )}

      {/* TAB 2: ROUTE SPECS */}
      {activeTab === 'routes' && (
        <div className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-3xl overflow-hidden shadow-sm animate-in fade-in">
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs border-collapse">
              <thead>
                <tr className="border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50/70 dark:bg-neutral-800/60 text-[10px] uppercase font-bold text-neutral-500 tracking-wider">
                  <th className="py-3 px-4">Route Path</th>
                  <th className="py-3 px-4">Component File</th>
                  <th className="py-3 px-4">Role Required</th>
                  <th className="py-3 px-4">Key Features</th>
                  <th className="py-3 px-4">Scope Description</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800 text-neutral-800 dark:text-neutral-200 font-mono text-[11px]">
                {ADMIN_ROUTES_SPEC.map((route, i) => (
                  <tr key={i} className="hover:bg-neutral-50/50 dark:hover:bg-neutral-800/50">
                    <td className="py-3 px-4 text-indigo-600 dark:text-indigo-400 font-bold">
                      {route.path}
                    </td>
                    <td className="py-3 px-4 text-neutral-700 dark:text-neutral-300">
                      {route.component}
                    </td>
                    <td className="py-3 px-4">
                      <span className="px-2 py-0.5 rounded bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400">
                        {route.authRole}
                      </span>
                    </td>
                    <td className="py-3 px-4 font-sans text-xs">
                      <div className="flex flex-wrap gap-1">
                        {(route.keyFeatures || []).map(f => (
                          <span key={f} className="px-1.5 py-0.2 rounded bg-neutral-100 dark:bg-neutral-800 text-[10px] text-neutral-600 dark:text-neutral-300">
                            {f}
                          </span>
                        ))}
                      </div>
                    </td>
                    <td className="py-3 px-4 font-sans text-xs text-neutral-500">
                      {route.description}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* TAB 3: JSON SCHEMAS */}
      {activeTab === 'schemas' && (
        <div className="space-y-6 animate-in fade-in">
          {Object.entries(PHASE2_JSON_SCHEMAS).map(([key, schema]) => (
            <div
              key={key}
              className="rounded-3xl border border-neutral-200 dark:border-neutral-800 bg-neutral-950 p-5 shadow-xl space-y-3"
            >
              <div className="flex items-center justify-between pb-3 border-b border-neutral-800">
                <div className="flex items-center gap-2">
                  <span className="w-2.5 h-2.5 rounded-full bg-cyan-400"></span>
                  <h3 className="text-xs font-bold uppercase tracking-wider text-neutral-200">
                    {key} Schema Definition
                  </h3>
                </div>

                <button
                  onClick={() => copyToClipboard(JSON.stringify(schema, null, 2), key)}
                  className="px-2.5 py-1 rounded-lg bg-neutral-800 text-neutral-300 hover:text-white text-xs flex items-center gap-1 font-sans"
                >
                  {copiedKey === key ? <Check className="w-3 h-3 text-emerald-400" /> : <Copy className="w-3 h-3" />}
                  <span>{copiedKey === key ? 'Copied' : 'Copy JSON'}</span>
                </button>
              </div>

              <pre className="text-emerald-400 font-mono text-[11px] overflow-x-auto max-h-72 p-2">
                {JSON.stringify(schema, null, 2)}
              </pre>
            </div>
          ))}
        </div>
      )}
    </div>
  );
};
