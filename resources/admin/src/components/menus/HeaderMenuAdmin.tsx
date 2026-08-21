import React, { useState } from 'react';
import { useAdmin } from '../../context/AdminContext';
import { HeaderMenuL1, HeaderMenuL2, HeaderMenuL3, MenuLinkType } from '../../types';
import {
  MenuSquare,
  Plus,
  Trash2,
  Edit2,
  ChevronDown,
  ChevronRight,
  Sparkles,
  Laptop,
  FolderTree,
  ExternalLink,
  Layers,
  Eye,
  Check,
  X,
  ArrowUpDown,
  Tag
} from 'lucide-react';

export const HeaderMenuAdmin: React.FC = () => {
  const {
    headerMenus,
    saveHeaderMenuL1,
    deleteHeaderMenuL1,
    saveHeaderMenuL2,
    deleteHeaderMenuL2,
    saveHeaderMenuL3,
    deleteHeaderMenuL3,
    categories,
    collections
  } = useAdmin();

  // Active expanded nodes in tree
  const [expandedL1, setExpandedL1] = useState<Record<string, boolean>>({ 'l1-tech': true, 'l1-apparel': true });
  const [expandedL2, setExpandedL2] = useState<Record<string, boolean>>({ 'l2-headphones': true });

  // Modals state
  const [editingL1, setEditingL1] = useState<HeaderMenuL1 | null>(null);
  const [isL1ModalOpen, setIsL1ModalOpen] = useState(false);

  const [editingL2, setEditingL2] = useState<{ l1Id: string; item: HeaderMenuL2 } | null>(null);
  const [isL2ModalOpen, setIsL2ModalOpen] = useState(false);

  const [editingL3, setEditingL3] = useState<{ l1Id: string; l2Id: string; item: HeaderMenuL3 } | null>(null);
  const [isL3ModalOpen, setIsL3ModalOpen] = useState(false);

  // Live Storefront Simulator Hover State
  const [previewActiveL1, setPreviewActiveL1] = useState<string | null>('l1-tech');
  const [showLiveSimulator, setShowLiveSimulator] = useState(true);

  const toggleL1 = (id: string) => setExpandedL1(prev => ({ ...prev, [id]: !prev[id] }));
  const toggleL2 = (id: string) => setExpandedL2(prev => ({ ...prev, [id]: !prev[id] }));

  // Handle L1 Create
  const openNewL1Modal = () => {
    const newItem: HeaderMenuL1 = {
      id: 'l1-' + Date.now(),
      title: '',
      url: '/categories/new',
      linkType: 'category',
      displayStyle: 'megamenu',
      isActive: true,
      sortOrder: headerMenus.length + 1,
      columns: []
    };
    setEditingL1(newItem);
    setIsL1ModalOpen(true);
  };

  // Handle L2 Create
  const openNewL2Modal = (l1Id: string) => {
    const newItem: HeaderMenuL2 = {
      id: 'l2-' + Date.now(),
      title: '',
      url: '/categories/sub',
      linkType: 'category',
      isActive: true,
      sortOrder: 1,
      items: []
    };
    setEditingL2({ l1Id, item: newItem });
    setIsL2ModalOpen(true);
  };

  // Handle L3 Create
  const openNewL3Modal = (l1Id: string, l2Id: string) => {
    const newItem: HeaderMenuL3 = {
      id: 'l3-' + Date.now(),
      title: '',
      url: '/categories/item',
      linkType: 'category',
      isActive: true,
      sortOrder: 1
    };
    setEditingL3({ l1Id, l2Id, item: newItem });
    setIsL3ModalOpen(true);
  };

  return (
    <div className="space-y-6 pb-12">
      {/* Header Bar */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-5 rounded-3xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-sm">
        <div>
          <div className="flex items-center gap-2">
            <span className="px-2.5 py-0.5 text-[10px] font-bold uppercase rounded-full bg-cyan-500/20 text-cyan-700 dark:text-cyan-300 border border-cyan-500/30">
              Navigation Tree Engine
            </span>
            <span className="text-xs text-neutral-500">3-Tier Hierarchy Model</span>
          </div>
          <h2 className="text-lg sm:text-xl font-bold text-neutral-900 dark:text-white tracking-tight mt-1">
            Header Mega Menu Administration
          </h2>
          <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
            Design multi-column Mega Menus and standard dropdowns: Level 1 (Main Tab) → Level 2 (Sub Category / Column) → Level 3 (Leaf Link).
          </p>
        </div>

        <div className="flex items-center gap-2">
          <button
            onClick={() => setShowLiveSimulator(!showLiveSimulator)}
            className={`px-3.5 py-2 rounded-xl text-xs font-semibold border transition-all flex items-center gap-1.5 ${
              showLiveSimulator
                ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800'
                : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 border-neutral-200 dark:border-neutral-700'
            }`}
          >
            <Eye className="w-3.5 h-3.5" />
            <span>{showLiveSimulator ? 'Hide Simulator' : 'Live Store Simulator'}</span>
          </button>

          <button
            onClick={openNewL1Modal}
            className="px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition-all shadow-md shadow-indigo-500/20 flex items-center gap-1.5"
          >
            <Plus className="w-4 h-4" />
            <span>Add Level 1 Menu</span>
          </button>
        </div>
      </div>

      {/* Live Storefront Mega-Menu Simulator Panel */}
      {showLiveSimulator && (
        <div className="p-5 rounded-3xl bg-neutral-950 border border-neutral-800 text-white shadow-2xl overflow-hidden">
          <div className="flex items-center justify-between pb-3 border-b border-neutral-800 mb-4 text-xs">
            <div className="flex items-center gap-2">
              <span className="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
              <span className="font-bold text-neutral-200">Interactive Storefront Preview (Customer Viewport)</span>
            </div>
            <span className="text-[11px] text-neutral-400">Hover or click tabs below to test mega menu responsiveness</span>
          </div>

          {/* Simulated Browser Navbar */}
          <div className="bg-neutral-900/90 rounded-2xl border border-neutral-800 p-3 shadow-inner">
            {/* Brand & Tabs */}
            <div className="flex items-center justify-between px-2">
              <div className="flex items-center gap-6">
                <span className="font-black tracking-widest text-sm text-white">AURORA</span>
                <nav className="flex items-center gap-1">
                  {headerMenus.map((l1) => (
                    <button
                      key={l1.id}
                      onClick={() => setPreviewActiveL1(l1.id)}
                      onMouseEnter={() => setPreviewActiveL1(l1.id)}
                      className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-all flex items-center gap-1.5 ${
                        previewActiveL1 === l1.id
                          ? 'bg-neutral-800 text-white shadow-xs'
                          : 'text-neutral-400 hover:text-white hover:bg-neutral-800/50'
                      }`}
                    >
                      <span>{l1.title}</span>
                      {l1.badge && (
                        <span className="text-[9px] px-1.5 py-0.2 rounded bg-indigo-500/30 text-indigo-300 border border-indigo-500/40">
                          {l1.badge}
                        </span>
                      )}
                      {l1.columns.length > 0 && <ChevronDown className="w-3 h-3 opacity-60" />}
                    </button>
                  ))}
                </nav>
              </div>

              <div className="text-[11px] text-neutral-400 flex items-center gap-3">
                <span className="hidden sm:inline text-neutral-500">Curated Architecture</span>
                <span className="px-2 py-0.5 rounded bg-neutral-800 text-neutral-300 text-[10px] font-mono">Cart (0)</span>
              </div>
            </div>

            {/* Render Active Mega Menu Dropdown */}
            {(() => {
              const active = headerMenus.find(m => m.id === previewActiveL1);
              if (!active || active.columns.length === 0) {
                return (
                  <div className="mt-3 p-4 bg-neutral-950/80 rounded-xl border border-neutral-800/60 text-center text-xs text-neutral-500">
                    Direct link or empty column structure for &quot;{active?.title || 'Selected Menu'}&quot;
                  </div>
                );
              }

              return (
                <div className="mt-3 p-5 bg-neutral-950 rounded-xl border border-neutral-800 animate-in fade-in slide-in-from-top-1 duration-150">
                  <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    {active.columns.map((col) => (
                      <div key={col.id} className="space-y-3">
                        <div className="flex items-center justify-between pb-1.5 border-b border-neutral-800">
                          <div className="flex items-center gap-1.5 text-xs font-bold text-white">
                            <span>{col.title}</span>
                            {col.badge && (
                              <span className="text-[9px] px-1.5 py-0.2 rounded bg-cyan-500/20 text-cyan-300 border border-cyan-500/30">
                                {col.badge}
                              </span>
                            )}
                          </div>
                        </div>

                        <ul className="space-y-1.5">
                          {col.items.map((leaf) => (
                            <li key={leaf.id}>
                              <a
                                href="#preview"
                                onClick={(e) => e.preventDefault()}
                                className="group flex items-center justify-between text-xs text-neutral-400 hover:text-white transition-colors"
                              >
                                <span>{leaf.title}</span>
                                {leaf.badge && (
                                  <span className="text-[9px] font-bold px-1 rounded bg-rose-500/20 text-rose-400 border border-rose-500/30">
                                    {leaf.badge}
                                  </span>
                                )}
                              </a>
                            </li>
                          ))}
                          {col.items.length === 0 && (
                            <li className="text-[11px] text-neutral-600 italic">No Level 3 links</li>
                          )}
                        </ul>
                      </div>
                    ))}
                  </div>
                </div>
              );
            })()}
          </div>
        </div>
      )}

      {/* 3-Tier Interactive Tree Editor */}
      <div className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-3xl p-5 shadow-sm space-y-4">
        <div className="flex items-center justify-between border-b border-neutral-100 dark:border-neutral-800 pb-3">
          <div className="flex items-center gap-2">
            <FolderTree className="w-4 h-4 text-indigo-500" />
            <h3 className="text-sm font-bold text-neutral-900 dark:text-white">Active Header Menu Structure</h3>
          </div>
          <span className="text-xs text-neutral-400 font-mono">{headerMenus.length} Level 1 Root Menus</span>
        </div>

        {/* Tree Nodes List */}
        <div className="space-y-3">
          {headerMenus.map((l1, l1Index) => {
            const isExpanded = expandedL1[l1.id];

            return (
              <div
                key={l1.id}
                className="rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-neutral-50/70 dark:bg-neutral-800/30 overflow-hidden"
              >
                {/* Level 1 Item Row */}
                <div className="p-3.5 flex items-center justify-between gap-3 bg-white dark:bg-neutral-800/80 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition-colors">
                  <div className="flex items-center gap-3 min-w-0">
                    <button
                      onClick={() => toggleL1(l1.id)}
                      className="p-1 rounded-lg text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200"
                    >
                      {isExpanded ? (
                        <ChevronDown className="w-4 h-4 text-indigo-500" />
                      ) : (
                        <ChevronRight className="w-4 h-4" />
                      )}
                    </button>

                    <div className="w-6 h-6 rounded-lg bg-indigo-100 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold text-xs">
                      1
                    </div>

                    <div className="min-w-0">
                      <div className="flex items-center gap-2 flex-wrap">
                        <span className="text-xs font-bold text-neutral-900 dark:text-white">{l1.title}</span>
                        {l1.badge && (
                          <span className="text-[10px] font-semibold px-2 py-0.2 rounded-md bg-indigo-100 dark:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300">
                            {l1.badge}
                          </span>
                        )}
                        <span className="text-[10px] font-mono px-1.5 py-0.2 rounded bg-neutral-100 dark:bg-neutral-700 text-neutral-500 dark:text-neutral-400">
                          {l1.displayStyle}
                        </span>
                        {!l1.isActive && (
                          <span className="text-[10px] font-semibold px-1.5 py-0.2 rounded bg-rose-100 text-rose-600 dark:bg-rose-950/50 dark:text-rose-400">
                            Disabled
                          </span>
                        )}
                      </div>
                      <p className="text-[11px] text-neutral-400 font-mono mt-0.5 truncate">{l1.url} ({l1.columns.length} columns)</p>
                    </div>
                  </div>

                  {/* Level 1 Action Buttons */}
                  <div className="flex items-center gap-1.5 flex-shrink-0">
                    <button
                      onClick={() => openNewL2Modal(l1.id)}
                      className="px-2.5 py-1 text-[11px] font-semibold rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-100 dark:hover:bg-indigo-900 transition-colors flex items-center gap-1"
                    >
                      <Plus className="w-3 h-3" />
                      <span>Add Sub (L2)</span>
                    </button>

                    <button
                      onClick={() => {
                        setEditingL1(l1);
                        setIsL1ModalOpen(true);
                      }}
                      className="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
                      title="Edit Level 1 Menu"
                    >
                      <Edit2 className="w-3.5 h-3.5" />
                    </button>

                    <button
                      onClick={() => deleteHeaderMenuL1(l1.id)}
                      className="p-1.5 rounded-lg text-rose-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30 transition-colors"
                      title="Delete Level 1 Menu"
                    >
                      <Trash2 className="w-3.5 h-3.5" />
                    </button>
                  </div>
                </div>

                {/* Level 2 Sub-categories / Columns */}
                {isExpanded && (
                  <div className="p-3 pl-8 sm:pl-12 space-y-3 border-t border-neutral-200 dark:border-neutral-800 bg-neutral-100/40 dark:bg-neutral-900/40">
                    {l1.columns.map((l2) => {
                      const isL2Expanded = expandedL2[l2.id] ?? true;

                      return (
                        <div
                          key={l2.id}
                          className="rounded-xl border border-neutral-200 dark:border-neutral-700/80 bg-white dark:bg-neutral-800/90 overflow-hidden"
                        >
                          {/* Level 2 Item Row */}
                          <div className="p-3 flex items-center justify-between gap-3">
                            <div className="flex items-center gap-2.5 min-w-0">
                              <button
                                onClick={() => toggleL2(l2.id)}
                                className="p-1 rounded text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200"
                              >
                                {isL2Expanded ? (
                                  <ChevronDown className="w-3.5 h-3.5 text-cyan-500" />
                                ) : (
                                  <ChevronRight className="w-3.5 h-3.5" />
                                )}
                              </button>

                              <div className="w-5 h-5 rounded-md bg-cyan-100 dark:bg-cyan-950/60 text-cyan-600 dark:text-cyan-400 flex items-center justify-center font-bold text-[10px]">
                                2
                              </div>

                              <div className="min-w-0">
                                <div className="flex items-center gap-2">
                                  <span className="text-xs font-bold text-neutral-900 dark:text-white truncate">
                                    {l2.title}
                                  </span>
                                  {l2.badge && (
                                    <span className="text-[9px] px-1.5 py-0.2 rounded bg-cyan-100 dark:bg-cyan-950 text-cyan-700 dark:text-cyan-300">
                                      {l2.badge}
                                    </span>
                                  )}
                                </div>
                                <span className="text-[10px] text-neutral-400 font-mono truncate block">
                                  {l2.url} • {l2.items.length} leaf links
                                </span>
                              </div>
                            </div>

                            {/* Level 2 Actions */}
                            <div className="flex items-center gap-1">
                              <button
                                onClick={() => openNewL3Modal(l1.id, l2.id)}
                                className="px-2 py-0.5 text-[10px] font-semibold rounded bg-cyan-50 dark:bg-cyan-950/60 text-cyan-600 dark:text-cyan-400 hover:bg-cyan-100 transition-colors flex items-center gap-1"
                              >
                                <Plus className="w-2.5 h-2.5" />
                                <span>Add Leaf (L3)</span>
                              </button>

                              <button
                                onClick={() => {
                                  setEditingL2({ l1Id: l1.id, item: l2 });
                                  setIsL2ModalOpen(true);
                                }}
                                className="p-1 rounded text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200"
                              >
                                <Edit2 className="w-3 h-3" />
                              </button>

                              <button
                                onClick={() => deleteHeaderMenuL2(l1.id, l2.id)}
                                className="p-1 rounded text-rose-400 hover:text-rose-600"
                              >
                                <Trash2 className="w-3 h-3" />
                              </button>
                            </div>
                          </div>

                          {/* Level 3 Leaf Items */}
                          {isL2Expanded && l2.items.length > 0 && (
                            <div className="p-2.5 pl-6 sm:pl-8 space-y-1.5 border-t border-neutral-100 dark:border-neutral-700/50 bg-neutral-50 dark:bg-neutral-900/50">
                              {l2.items.map((l3) => (
                                <div
                                  key={l3.id}
                                  className="flex items-center justify-between p-2 rounded-lg bg-white dark:bg-neutral-800 border border-neutral-200/80 dark:border-neutral-700/60 text-xs"
                                >
                                  <div className="flex items-center gap-2 min-w-0">
                                    <div className="w-4 h-4 rounded bg-neutral-100 dark:bg-neutral-700 text-neutral-500 dark:text-neutral-400 flex items-center justify-center font-mono text-[9px]">
                                      3
                                    </div>
                                    <span className="font-medium text-neutral-900 dark:text-white truncate">
                                      {l3.title}
                                    </span>
                                    {l3.badge && (
                                      <span className="text-[8px] font-bold px-1 py-0.2 rounded bg-rose-100 dark:bg-rose-950 text-rose-600 dark:text-rose-400">
                                        {l3.badge}
                                      </span>
                                    )}
                                    <span className="text-[10px] text-neutral-400 font-mono truncate">
                                      {l3.url}
                                    </span>
                                  </div>

                                  <div className="flex items-center gap-1">
                                    <button
                                      onClick={() => {
                                        setEditingL3({ l1Id: l1.id, l2Id: l2.id, item: l3 });
                                        setIsL3ModalOpen(true);
                                      }}
                                      className="p-1 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200"
                                    >
                                      <Edit2 className="w-2.5 h-2.5" />
                                    </button>
                                    <button
                                      onClick={() => deleteHeaderMenuL3(l1.id, l2.id, l3.id)}
                                      className="p-1 text-rose-400 hover:text-rose-600"
                                    >
                                      <Trash2 className="w-2.5 h-2.5" />
                                    </button>
                                  </div>
                                </div>
                              ))}
                            </div>
                          )}
                        </div>
                      );
                    })}

                    {l1.columns.length === 0 && (
                      <div className="p-4 text-center text-xs text-neutral-400 border border-dashed border-neutral-300 dark:border-neutral-700 rounded-xl">
                        No columns created yet. Click &quot;Add Sub (L2)&quot; to build Mega-Menu columns.
                      </div>
                    )}
                  </div>
                )}
              </div>
            );
          })}
        </div>
      </div>

      {/* Edit Level 1 Modal */}
      {isL1ModalOpen && editingL1 && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs animate-in fade-in">
          <div className="w-full max-w-md bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-3xl p-6 shadow-2xl">
            <div className="flex items-center justify-between pb-3 border-b border-neutral-100 dark:border-neutral-800 mb-4">
              <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
                {editingL1.id.startsWith('l1-') && editingL1.title === '' ? 'Create Level 1 Main Menu' : 'Edit Level 1 Main Menu'}
              </h3>
              <button onClick={() => setIsL1ModalOpen(false)} className="text-neutral-400 hover:text-neutral-600">
                <X className="w-4 h-4" />
              </button>
            </div>

            <form
              onSubmit={(e) => {
                e.preventDefault();
                saveHeaderMenuL1(editingL1);
                setIsL1ModalOpen(false);
              }}
              className="space-y-4"
            >
              <div>
                <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Menu Title</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Electronics & Audio"
                  value={editingL1.title}
                  onChange={(e) => setEditingL1({ ...editingL1, title: e.target.value })}
                  className="w-full px-3 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white focus:ring-2 focus:ring-indigo-500"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">URL Path</label>
                <input
                  type="text"
                  required
                  placeholder="/categories/electronics"
                  value={editingL1.url}
                  onChange={(e) => setEditingL1({ ...editingL1, url: e.target.value })}
                  className="w-full px-3 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white font-mono"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Display Style</label>
                  <select
                    value={editingL1.displayStyle}
                    onChange={(e) => setEditingL1({ ...editingL1, displayStyle: e.target.value as HeaderMenuL1['displayStyle'] })}
                    className="w-full px-3 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
                  >
                    <option value="megamenu">Mega Menu (Multi-col)</option>
                    <option value="dropdown">Standard Dropdown</option>
                    <option value="simple_link">Direct Link Only</option>
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Badge Tag (Optional)</label>
                  <input
                    type="text"
                    placeholder="e.g. HOT, NEW"
                    value={editingL1.badge || ''}
                    onChange={(e) => setEditingL1({ ...editingL1, badge: e.target.value || undefined })}
                    className="w-full px-3 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
                  />
                </div>
              </div>

              <div className="flex items-center justify-between py-2">
                <label className="flex items-center gap-2 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={editingL1.isActive}
                    onChange={(e) => setEditingL1({ ...editingL1, isActive: e.target.checked })}
                    className="rounded border-neutral-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4"
                  />
                  <span className="text-xs font-medium text-neutral-700 dark:text-neutral-300">Active on Storefront</span>
                </label>
              </div>

              <div className="flex justify-end gap-2 pt-3 border-t border-neutral-100 dark:border-neutral-800">
                <button
                  type="button"
                  onClick={() => setIsL1ModalOpen(false)}
                  className="px-4 py-2 text-xs font-medium rounded-xl border border-neutral-300 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-4 py-2 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white shadow-md"
                >
                  Save Level 1
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Edit Level 2 Modal */}
      {isL2ModalOpen && editingL2 && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs animate-in fade-in">
          <div className="w-full max-w-md bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-3xl p-6 shadow-2xl">
            <div className="flex items-center justify-between pb-3 border-b border-neutral-100 dark:border-neutral-800 mb-4">
              <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
                Sub Category Column (Level 2)
              </h3>
              <button onClick={() => setIsL2ModalOpen(false)} className="text-neutral-400 hover:text-neutral-600">
                <X className="w-4 h-4" />
              </button>
            </div>

            <form
              onSubmit={(e) => {
                e.preventDefault();
                saveHeaderMenuL2(editingL2.l1Id, editingL2.item);
                setIsL2ModalOpen(false);
              }}
              className="space-y-4"
            >
              <div>
                <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Column Title</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Premium Audio"
                  value={editingL2.item.title}
                  onChange={(e) => setEditingL2({ ...editingL2, item: { ...editingL2.item, title: e.target.value } })}
                  className="w-full px-3 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">URL Path</label>
                <input
                  type="text"
                  required
                  placeholder="/categories/audio"
                  value={editingL2.item.url}
                  onChange={(e) => setEditingL2({ ...editingL2, item: { ...editingL2.item, url: e.target.value } })}
                  className="w-full px-3 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white font-mono"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Link Type</label>
                  <select
                    value={editingL2.item.linkType}
                    onChange={(e) => setEditingL2({ ...editingL2, item: { ...editingL2.item, linkType: e.target.value as MenuLinkType } })}
                    className="w-full px-3 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
                  >
                    <option value="category">Category</option>
                    <option value="collection">Collection</option>
                    <option value="page">Page</option>
                    <option value="custom_url">Custom URL</option>
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Badge Tag</label>
                  <input
                    type="text"
                    placeholder="e.g. Popular"
                    value={editingL2.item.badge || ''}
                    onChange={(e) => setEditingL2({ ...editingL2, item: { ...editingL2.item, badge: e.target.value || undefined } })}
                    className="w-full px-3 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
                  />
                </div>
              </div>

              <div className="flex justify-end gap-2 pt-3 border-t border-neutral-100 dark:border-neutral-800">
                <button
                  type="button"
                  onClick={() => setIsL2ModalOpen(false)}
                  className="px-4 py-2 text-xs font-medium rounded-xl border border-neutral-300 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-4 py-2 text-xs font-bold rounded-xl bg-cyan-600 hover:bg-cyan-700 text-white shadow-md"
                >
                  Save Column (L2)
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Edit Level 3 Modal */}
      {isL3ModalOpen && editingL3 && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs animate-in fade-in">
          <div className="w-full max-w-md bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-3xl p-6 shadow-2xl">
            <div className="flex items-center justify-between pb-3 border-b border-neutral-100 dark:border-neutral-800 mb-4">
              <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
                Leaf Item Link (Level 3)
              </h3>
              <button onClick={() => setIsL3ModalOpen(false)} className="text-neutral-400 hover:text-neutral-600">
                <X className="w-4 h-4" />
              </button>
            </div>

            <form
              onSubmit={(e) => {
                e.preventDefault();
                saveHeaderMenuL3(editingL3.l1Id, editingL3.l2Id, editingL3.item);
                setIsL3ModalOpen(false);
              }}
              className="space-y-4"
            >
              <div>
                <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Item Title</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Noise Cancelling Headphones"
                  value={editingL3.item.title}
                  onChange={(e) => setEditingL3({ ...editingL3, item: { ...editingL3.item, title: e.target.value } })}
                  className="w-full px-3 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Target URL</label>
                <input
                  type="text"
                  required
                  placeholder="/categories/anc-headphones"
                  value={editingL3.item.url}
                  onChange={(e) => setEditingL3({ ...editingL3, item: { ...editingL3.item, url: e.target.value } })}
                  className="w-full px-3 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white font-mono"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Link Type</label>
                  <select
                    value={editingL3.item.linkType}
                    onChange={(e) => setEditingL3({ ...editingL3, item: { ...editingL3.item, linkType: e.target.value as MenuLinkType } })}
                    className="w-full px-3 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
                  >
                    <option value="category">Category</option>
                    <option value="collection">Collection</option>
                    <option value="product">Product</option>
                    <option value="custom_url">External URL</option>
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Badge (e.g. HOT)</label>
                  <input
                    type="text"
                    placeholder="HOT, NEW"
                    value={editingL3.item.badge || ''}
                    onChange={(e) => setEditingL3({ ...editingL3, item: { ...editingL3.item, badge: e.target.value || undefined } })}
                    className="w-full px-3 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
                  />
                </div>
              </div>

              <div className="flex justify-end gap-2 pt-3 border-t border-neutral-100 dark:border-neutral-800">
                <button
                  type="button"
                  onClick={() => setIsL3ModalOpen(false)}
                  className="px-4 py-2 text-xs font-medium rounded-xl border border-neutral-300 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-4 py-2 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white shadow-md"
                >
                  Save Leaf Item (L3)
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
