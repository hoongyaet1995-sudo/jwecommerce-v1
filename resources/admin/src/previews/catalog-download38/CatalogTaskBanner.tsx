import React from 'react';
import {CloudDownload} from 'lucide-react';
import type {CatalogDownloadTask38} from './catalogDownload38';
export function CatalogTaskBanner({task,onOpen}:{task:CatalogDownloadTask38;onOpen:()=>void}){return (
        <div className="p-3.5 rounded-xl bg-indigo-950/40 border border-indigo-800/60 flex items-center justify-between gap-4 animate-fadeIn">
          <div className="flex items-center gap-3">
            <div className="w-8 h-8 rounded-lg bg-indigo-500/20 border border-indigo-500/40 flex items-center justify-center text-indigo-300 shrink-0">
              <CloudDownload className="w-4 h-4 animate-pulse" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <span className="text-xs font-bold text-indigo-100">
                  A catalog download is already running for {task.shopName}.
                </span>
                <span className="text-[10px] px-2 py-0.5 rounded-full font-bold bg-indigo-500/20 text-indigo-300">
                  {task.totalProducts ? `${Math.round((task.downloadedProducts / task.totalProducts) * 100)}%` : 'Confirming size…'}
                </span>
              </div>
              <p className="text-[11px] text-indigo-300/80 mt-0.5">
                {task.downloadedProducts.toLocaleString()} products retrieved • Page {task.currentPage} • Status: {task.status}.
              </p>
            </div>
          </div>
          <button
            type="button"
            onClick={() => onOpen()}
            className="px-3.5 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition-all shadow-sm cursor-pointer whitespace-nowrap"
          >
            View progress
          </button>
        </div>
);}
