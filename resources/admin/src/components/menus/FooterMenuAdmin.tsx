import React, { useState } from 'react';
import { useAdmin } from '../../context/AdminContext';
import { FooterMenuL1, FooterMenuL2, MenuLinkType } from '../../types';
import { DeleteConfirmationModal } from '../common/DeleteConfirmationModal';
import { MenuLinkTargetPicker } from './MenuLinkTargetPicker';
import { MenuBadgeEditor } from './MenuBadgeEditor';
import { changeMenuLinkType } from '../../integration/menuLinkTargets';
import {
  PanelBottom,
  Plus,
  Trash2,
  Edit2,
  ExternalLink,
  Eye,
  X,
  Layers,
  ChevronDown,
  ChevronRight,
  Sparkles
} from 'lucide-react';

export const FooterMenuAdmin: React.FC = () => {
  const {
    footerMenus,
    saveFooterMenuL1,
    deleteFooterMenuL1,
    saveFooterMenuL2,
    deleteFooterMenuL2,
    categories,
    collections,
    pages
  } = useAdmin();

  const [showFooterSimulator, setShowFooterSimulator] = useState(true);

  // Modals state
  const [editingL1, setEditingL1] = useState<FooterMenuL1 | null>(null);
  const [isL1ModalOpen, setIsL1ModalOpen] = useState(false);

  const [editingL2, setEditingL2] = useState<{ l1Id: string; item: FooterMenuL2 } | null>(null);
  const [isL2ModalOpen, setIsL2ModalOpen] = useState(false);
  const [l1ToDelete, setL1ToDelete] = useState<FooterMenuL1 | null>(null);
  const [l2ToDelete, setL2ToDelete] = useState<{ l1Id: string; item: FooterMenuL2 } | null>(null);

  const openNewL1Modal = () => {
    const newItem: FooterMenuL1 = {
      id: 'ft-' + Date.now(),
      sectionTitle: '',
      isActive: true,
      sortOrder: footerMenus.length + 1,
      links: []
    };
    setEditingL1(newItem);
    setIsL1ModalOpen(true);
  };

  const openNewL2Modal = (l1Id: string) => {
    const newItem: FooterMenuL2 = {
      id: 'ftl-' + Date.now(),
      title: '',
      url: '',
      linkType: 'page',
      isActive: true,
      sortOrder: 1
    };
    setEditingL2({ l1Id, item: newItem });
    setIsL2ModalOpen(true);
  };

  return (
    <div className="space-y-6 pb-12">
      {/* Header Bar */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-5 rounded-3xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-sm">
        <div>
          <div className="flex items-center gap-2">
            <span className="px-2.5 py-0.5 text-[10px] font-bold uppercase rounded-full bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 border border-indigo-500/30">
              Footer Builder
            </span>
            <span className="text-xs text-neutral-500">2-Tier Column Hierarchy</span>
          </div>
          <h2 className="text-lg sm:text-xl font-bold text-neutral-900 dark:text-white tracking-tight mt-1">
            Footer Navigation & Column Admin
          </h2>
          <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
            Structure customer care links, company information, legal documents, and developer partner resources: Section Header (L1) → Link Item (L2).
          </p>
        </div>

        <div className="flex items-center gap-2">
          <button
            onClick={() => setShowFooterSimulator(!showFooterSimulator)}
            className={`px-3.5 py-2 rounded-xl text-xs font-semibold border transition-all flex items-center gap-1.5 ${
              showFooterSimulator
                ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800'
                : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 border-neutral-200 dark:border-neutral-700'
            }`}
          >
            <Eye className="w-3.5 h-3.5" />
            <span>{showFooterSimulator ? 'Hide Footer Preview' : 'Live Store Footer'}</span>
          </button>

          <button
            onClick={openNewL1Modal}
            className="px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition-all shadow-md shadow-indigo-500/20 flex items-center gap-1.5"
          >
            <Plus className="w-4 h-4" />
            <span>Add Footer Section</span>
          </button>
        </div>
      </div>

      {/* Live Storefront Footer Simulator */}
      {showFooterSimulator && (
        <div className="p-6 rounded-3xl bg-neutral-950 border border-neutral-800 text-white shadow-2xl space-y-6">
          <div className="flex items-center justify-between pb-3 border-b border-neutral-800 text-xs">
            <div className="flex items-center gap-2">
              <span className="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
              <span className="font-bold text-neutral-200">Customer Viewport: Live Storefront Footer</span>
            </div>
            <span className="text-[11px] text-neutral-400">Desktop 4-Column Layout Preview</span>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-8 py-2">
            {footerMenus.map((sec) => (
              <div key={sec.id} className="space-y-3">
                <h4 className="text-xs font-bold tracking-wider uppercase text-neutral-200 pb-1 border-b border-neutral-800/80">
                  {sec.sectionTitle}
                </h4>
                <ul className="space-y-2">
                  {sec.links.map((link) => (
                    <li key={link.id}>
                      <a
                        href="#preview"
                        onClick={(e) => e.preventDefault()}
                        className="group flex items-center gap-1.5 text-xs text-neutral-400 hover:text-white transition-colors"
                      >
                        <span>{link.title}</span>
                        {link.badge && (
                          <span className="text-[9px] px-1 py-0.2 rounded bg-indigo-500/30 text-indigo-300 font-medium">
                            {link.badge}
                          </span>
                        )}
                        {link.isExternal && <ExternalLink className="w-2.5 h-2.5 opacity-60" />}
                      </a>
                    </li>
                  ))}
                  {sec.links.length === 0 && (
                    <li className="text-[11px] text-neutral-600 italic">No links in this section</li>
                  )}
                </ul>
              </div>
            ))}
          </div>

          <div className="pt-4 border-t border-neutral-900 flex flex-col sm:flex-row items-center justify-between gap-2 text-[11px] text-neutral-500">
            <span>© 2026 Aurora Commerce Inc. All rights reserved.</span>
            <div className="flex gap-4">
              <span>GDPR / CCPA Ready</span>
              <span>PCI DSS Certified</span>
            </div>
          </div>
        </div>
      )}

      {/* 2-Tier Hierarchy Admin Sections */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
        {footerMenus.map((sec) => (
          <div
            key={sec.id}
            className="rounded-3xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 p-5 shadow-sm space-y-4"
          >
            {/* Section Title Header */}
            <div className="flex items-center justify-between pb-3 border-b border-neutral-100 dark:border-neutral-800">
              <div className="flex items-center gap-2.5 min-w-0">
                <div className="w-7 h-7 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold text-xs">
                  §
                </div>
                <div className="min-w-0">
                  <h3 className="text-sm font-bold text-neutral-900 dark:text-white truncate">
                    {sec.sectionTitle}
                  </h3>
                  <p className="text-[11px] text-neutral-400 font-mono">{sec.links.length} child links</p>
                </div>
              </div>

              <div className="flex items-center gap-1">
                <button
                  onClick={() => openNewL2Modal(sec.id)}
                  className="px-2.5 py-1 text-[11px] font-semibold rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-100 transition-colors flex items-center gap-1"
                >
                  <Plus className="w-3 h-3" />
                  <span>Add Link</span>
                </button>

                <button
                  onClick={() => {
                    setEditingL1(sec);
                    setIsL1ModalOpen(true);
                  }}
                  className="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800"
                >
                  <Edit2 className="w-3.5 h-3.5" />
                </button>

                <button
                  onClick={() => setL1ToDelete(sec)}
                  className="p-1.5 rounded-lg text-rose-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30"
                >
                  <Trash2 className="w-3.5 h-3.5" />
                </button>
              </div>
            </div>

            {/* Link Items list */}
            <div className="space-y-2">
              {sec.links.map((link) => (
                <div
                  key={link.id}
                  className="flex items-center justify-between p-2.5 rounded-xl border border-neutral-200/80 dark:border-neutral-800 bg-neutral-50/50 dark:bg-neutral-800/40 text-xs"
                >
                  <div className="flex items-center gap-2 min-w-0">
                    <span className="font-medium text-neutral-900 dark:text-white truncate">
                      {link.title}
                    </span>
                    {link.badge && (
                      <span className="text-[9px] font-bold px-1.5 py-0.2 rounded bg-indigo-100 dark:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300">
                        {link.badge}
                      </span>
                    )}
                    {link.isExternal && (
                      <span className="text-[9px] px-1 rounded bg-neutral-200 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-400 font-mono">
                        External
                      </span>
                    )}
                    <span className="text-[10px] text-neutral-400 font-mono truncate">
                      {link.url}
                    </span>
                  </div>

                  <div className="flex items-center gap-1">
                    <button
                      onClick={() => {
                        setEditingL2({ l1Id: sec.id, item: link });
                        setIsL2ModalOpen(true);
                      }}
                      className="p-1 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200"
                    >
                      <Edit2 className="w-3 h-3" />
                    </button>
                    <button
                      onClick={() => setL2ToDelete({ l1Id: sec.id, item: link })}
                      className="p-1 text-rose-400 hover:text-rose-600"
                    >
                      <Trash2 className="w-3 h-3" />
                    </button>
                  </div>
                </div>
              ))}

              {sec.links.length === 0 && (
                <p className="text-center text-xs text-neutral-400 py-3 italic">
                  No links in this column.
                </p>
              )}
            </div>
          </div>
        ))}
      </div>

      {/* Edit Section Modal */}
      {isL1ModalOpen && editingL1 && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs animate-in fade-in">
          <div className="w-full max-w-md bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-3xl p-6 shadow-2xl">
            <div className="flex items-center justify-between pb-3 border-b border-neutral-100 dark:border-neutral-800 mb-4">
              <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
                Footer Section Header (Level 1)
              </h3>
              <button onClick={() => setIsL1ModalOpen(false)} className="text-neutral-400 hover:text-neutral-600">
                <X className="w-4 h-4" />
              </button>
            </div>

            <form
              onSubmit={(e) => {
                e.preventDefault();
                saveFooterMenuL1(editingL1);
                setIsL1ModalOpen(false);
              }}
              className="space-y-4"
            >
              <div>
                <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Section Title</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Customer Care & Support"
                  value={editingL1.sectionTitle}
                  onChange={(e) => setEditingL1({ ...editingL1, sectionTitle: e.target.value })}
                  className="w-full px-3 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white focus:ring-2 focus:ring-indigo-500"
                />
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
                  Save Section
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Edit Link Modal */}
      {isL2ModalOpen && editingL2 && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs animate-in fade-in">
          <div className="w-full max-w-md bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-3xl p-6 shadow-2xl">
            <div className="flex items-center justify-between pb-3 border-b border-neutral-100 dark:border-neutral-800 mb-4">
              <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
                Footer Column Link (Level 2)
              </h3>
              <button onClick={() => setIsL2ModalOpen(false)} className="text-neutral-400 hover:text-neutral-600">
                <X className="w-4 h-4" />
              </button>
            </div>

            <form
              onSubmit={(e) => {
                e.preventDefault();
                saveFooterMenuL2(editingL2.l1Id, editingL2.item);
                setIsL2ModalOpen(false);
              }}
              className="space-y-4"
            >
              <div>
                <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Link Title</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Track Order & Shipping"
                  value={editingL2.item.title}
                  onChange={(e) => setEditingL2({ ...editingL2, item: { ...editingL2.item, title: e.target.value } })}
                  className="w-full px-3 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Link Type</label>
                  <select
                    value={editingL2.item.linkType}
                    onChange={(e) => setEditingL2({ ...editingL2, item: changeMenuLinkType(editingL2.item, e.target.value as MenuLinkType) })}
                    className="w-full px-3 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
                  >
                    <option value="page">Store Page</option>
                    <option value="custom_url">External URL</option>
                    <option value="category">Category</option>
                    <option value="collection">Collection</option>
                  </select>
                </div>
                <MenuBadgeEditor
                  badge={editingL2.item.badge}
                  badgeColor={editingL2.item.badgeColor}
                  onChange={({ badge, badgeColor }) =>
                    setEditingL2({ ...editingL2, item: { ...editingL2.item, badge, badgeColor } })
                  }
                />
              </div>

              <MenuLinkTargetPicker
                linkType={editingL2.item.linkType}
                targetId={editingL2.item.targetId}
                url={editingL2.item.url}
                categories={categories}
                collections={collections}
                pages={pages}
                onTargetChange={(targetId, url) => setEditingL2({ ...editingL2, item: { ...editingL2.item, targetId, url } })}
              />


              <div className="flex items-center gap-2">
                <input
                  type="checkbox"
                  id="chk-external"
                  checked={editingL2.item.isExternal || false}
                  onChange={(e) => setEditingL2({ ...editingL2, item: { ...editingL2.item, isExternal: e.target.checked } })}
                  className="rounded border-neutral-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4"
                />
                <label htmlFor="chk-external" className="text-xs font-medium text-neutral-700 dark:text-neutral-300 cursor-pointer">
                  Opens in New Window (target=&quot;_blank&quot;)
                </label>
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
                  className="px-4 py-2 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white shadow-md"
                >
                  Save Link
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
      <DeleteConfirmationModal isOpen={!!l1ToDelete} itemType="Footer Column Section" itemName={l1ToDelete?.sectionTitle} itemDetails={l1ToDelete ? `Child Links: ${l1ToDelete.links.length} items` : undefined} warningMessage={`Deleting "${l1ToDelete?.sectionTitle}" removes this entire footer column.`} impactItems={['All links inside this footer column will be removed.', 'Other footer columns remain unchanged.']} confirmButtonText="Delete Footer Column" onConfirm={() => { if (l1ToDelete) { deleteFooterMenuL1(l1ToDelete.id); setL1ToDelete(null); } }} onClose={() => setL1ToDelete(null)} />
      <DeleteConfirmationModal isOpen={!!l2ToDelete} itemType="Footer Link" itemName={l2ToDelete?.item.title} itemDetails={l2ToDelete ? `Target URL: ${l2ToDelete.item.url}` : undefined} warningMessage={`Removing "${l2ToDelete?.item.title}" removes this footer navigation link.`} impactItems={['Customers will no longer see this link in the footer.', 'The parent footer column remains unchanged.']} confirmButtonText="Remove Link" onConfirm={() => { if (l2ToDelete) { deleteFooterMenuL2(l2ToDelete.l1Id, l2ToDelete.item.id); setL2ToDelete(null); } }} onClose={() => setL2ToDelete(null)} />
    </div>
  );
};
