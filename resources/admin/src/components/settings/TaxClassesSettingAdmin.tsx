import React, { useState } from 'react';
import { useAdmin } from '../../context/AdminContext';
import { TaxClass } from '../../types';
import {
  Percent,
  Plus,
  Edit2,
  Trash2,
  CheckCircle2,
  AlertTriangle,
  AlertCircle,
  Info,
  Check,
  X,
  Power,
  Loader2,
  Layers,
  HelpCircle
} from 'lucide-react';

export const TaxClassesSettingAdmin: React.FC = () => {
  const {
    taxClasses,
    saveTaxClass,
    activateTaxClass,
    deactivateTaxClass,
    deleteTaxClass,
    addToast
  } = useAdmin();

  // Modals state
  const [isFormModalOpen, setIsFormModalOpen] = useState(false);
  const [editingClass, setEditingClass] = useState<TaxClass | null>(null);
  const [isSaving, setIsSaving] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);

  // Form inputs state
  const [formData, setFormData] = useState<{
    id?: string;
    name: string;
    ratePercent: string;
    isActive: boolean;
  }>({
    name: '',
    ratePercent: '8',
    isActive: false
  });

  // Confirmation Modals State
  const [activateConfirmTarget, setActivateConfirmTarget] = useState<TaxClass | null>(null);
  const [deactivateConfirmTarget, setDeactivateConfirmTarget] = useState<TaxClass | null>(null);
  const [deleteConfirmTarget, setDeleteConfirmTarget] = useState<TaxClass | null>(null);

  // Active Tax Class
  const currentActiveClass = taxClasses.find(tc => tc.isActive);

  // Format tax rate to prevent unnecessary trailing zeroes (e.g. 8%, 6%, 0%, 8.125%)
  const formatRate = (rate: number | undefined): string => {
    if (rate === undefined || isNaN(rate)) return '0%';
    const cleanNum = parseFloat(Number(rate).toFixed(4));
    return `${cleanNum}%`;
  };

  // Open Add Modal
  const handleOpenAdd = () => {
    setEditingClass(null);
    setFormError(null);
    setFormData({
      id: 'tc_' + Date.now(),
      name: '',
      ratePercent: '8',
      isActive: taxClasses.length === 0 // If it's the first class, default to active
    });
    setIsFormModalOpen(true);
  };

  // Open Edit Modal
  const handleOpenEdit = (tc: TaxClass) => {
    setEditingClass(tc);
    setFormError(null);
    setFormData({
      id: tc.id,
      name: tc.name,
      ratePercent: String(tc.ratePercent),
      isActive: tc.isActive
    });
    setIsFormModalOpen(true);
  };

  // Validate and submit Tax Class (Add / Edit)
  const handleSubmitForm = (e: React.FormEvent) => {
    e.preventDefault();
    setFormError(null);

    const trimmedName = formData.name.trim();

    // 1. Required Name Validation
    if (!trimmedName) {
      setFormError('Tax Class Name is required.');
      return;
    }

    // 2. Duplicate Name Validation
    const isDuplicate = taxClasses.some(
      tc => tc.name.toLowerCase() === trimmedName.toLowerCase() && tc.id !== formData.id
    );
    if (isDuplicate) {
      setFormError('A Tax Class with this name already exists.');
      return;
    }

    // 3. Rate Validation (0-100, up to 4 decimal places)
    const rateValue = parseFloat(formData.ratePercent);
    if (
      isNaN(rateValue) ||
      rateValue < 0 ||
      rateValue > 100 ||
      !/^\d+(\.\d{1,4})?$/.test(formData.ratePercent.trim())
    ) {
      setFormError('Tax Rate must be between 0 and 100 and use no more than four decimal places.');
      return;
    }

    setIsSaving(true);

    setTimeout(() => {
      const finalClass: TaxClass = {
        id: formData.id || 'tc_' + Date.now(),
        name: trimmedName,
        ratePercent: rateValue,
        isActive: formData.isActive,
        createdAt: editingClass?.createdAt || new Date().toISOString(),
        updatedAt: new Date().toISOString()
      };

      saveTaxClass(finalClass);
      setIsSaving(false);
      setIsFormModalOpen(false);
    }, 250);
  };

  // Handle Activate Click
  const handleActivateClick = (tc: TaxClass) => {
    if (currentActiveClass && currentActiveClass.id !== tc.id) {
      // Show confirmation dialog with both names
      setActivateConfirmTarget(tc);
    } else {
      // Activate directly if no other class is active
      activateTaxClass(tc.id);
    }
  };

  // Confirm Activation
  const confirmActivate = () => {
    if (activateConfirmTarget) {
      activateTaxClass(activateConfirmTarget.id);
      setActivateConfirmTarget(null);
    }
  };

  // Handle Deactivate Click
  const handleDeactivateClick = (tc: TaxClass) => {
    setDeactivateConfirmTarget(tc);
  };

  // Confirm Deactivation
  const confirmDeactivate = () => {
    if (deactivateConfirmTarget) {
      deactivateTaxClass(deactivateConfirmTarget.id);
      setDeactivateConfirmTarget(null);
    }
  };

  // Handle Delete Click
  const handleDeleteClick = (tc: TaxClass) => {
    if (tc.isActive) {
      addToast({
        type: 'error',
        title: 'Action Prohibited',
        message: 'Deactivate this Tax Class before deleting it.'
      });
      return;
    }
    setDeleteConfirmTarget(tc);
  };

  // Confirm Delete
  const confirmDelete = () => {
    if (deleteConfirmTarget) {
      deleteTaxClass(deleteConfirmTarget.id);
      setDeleteConfirmTarget(null);
    }
  };

  return (
    <div className="space-y-6 max-w-7xl mx-auto">
      {/* PAGE HEADER */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
        <div>
          <div className="flex items-center gap-2">
            <span className="px-2.5 py-0.5 text-[10px] font-bold uppercase rounded-full bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 border border-indigo-500/30">
              STORE TAX CONFIGURATION
            </span>
            <span className="text-xs text-neutral-500 dark:text-neutral-400 font-mono">
              /admin/settings/tax-classes
            </span>
          </div>
          <h2 className="text-lg sm:text-xl font-bold text-neutral-900 dark:text-white tracking-tight mt-1">
            Tax Classes
          </h2>
          <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5 max-w-2xl">
            Configure one store-wide percentage charge. Only one Tax Class can be active at a time.
          </p>
        </div>

        <button
          id="btn-add-tax-class"
          onClick={handleOpenAdd}
          className="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs transition cursor-pointer self-start sm:self-auto"
        >
          <Plus className="w-4 h-4" />
          <span>Add Tax Class</span>
        </button>
      </div>

      {/* UNCONFIGURED WARNING (When no Tax Class is active) */}
      {!currentActiveClass && (
        <div
          id="alert-unconfigured-tax"
          className="flex items-center gap-3 p-4 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-amber-900 dark:text-amber-200 shadow-xs"
        >
          <AlertTriangle className="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0" />
          <div className="text-xs sm:text-sm font-medium">
            No active Tax Class. New orders will not be charged tax.
          </div>
        </div>
      )}

      {/* TAX CLASSES TABLE */}
      <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs overflow-hidden">
        {taxClasses.length === 0 ? (
          /* Empty State */
          <div className="text-center py-16 px-4">
            <div className="w-14 h-14 rounded-2xl bg-neutral-100 dark:bg-neutral-800 flex items-center justify-center mx-auto mb-3 text-neutral-400 dark:text-neutral-500">
              <Percent className="w-7 h-7" />
            </div>
            <h3 className="text-base font-bold text-neutral-900 dark:text-white mb-1">
              No Tax Classes configured
            </h3>
            <p className="text-xs text-neutral-500 dark:text-neutral-400 max-w-sm mx-auto mb-5">
              Add a Tax Class to apply one store-wide percentage charge.
            </p>
            <button
              onClick={handleOpenAdd}
              className="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs transition cursor-pointer"
            >
              <Plus className="w-4 h-4" />
              Add Tax Class
            </button>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse text-xs">
              <thead>
                <tr className="border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50/80 dark:bg-neutral-800/50 text-neutral-500 dark:text-neutral-400 font-semibold uppercase tracking-wider text-[10px]">
                  <th className="py-3.5 px-5">Tax Class Name</th>
                  <th className="py-3.5 px-5">Tax Rate</th>
                  <th className="py-3.5 px-5">Status</th>
                  <th className="py-3.5 px-5 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-200 dark:divide-neutral-800">
                {taxClasses.map(tc => {
                  const isActive = tc.isActive;
                  return (
                    <tr
                      key={tc.id}
                      className={`group transition-colors ${
                        isActive
                          ? 'bg-emerald-50/40 dark:bg-emerald-950/20 hover:bg-emerald-50/70 dark:hover:bg-emerald-950/30'
                          : 'hover:bg-neutral-50 dark:hover:bg-neutral-800/60'
                      }`}
                    >
                      {/* Name */}
                      <td className="py-4 px-5">
                        <div className="flex items-center gap-2.5">
                          <div
                            className={`w-8 h-8 rounded-xl flex items-center justify-center shrink-0 ${
                              isActive
                                ? 'bg-emerald-100 dark:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300 ring-1 ring-emerald-500/30'
                                : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-500 dark:text-neutral-400'
                            }`}
                          >
                            <Percent className="w-4 h-4" />
                          </div>
                          <div>
                            <div className="font-semibold text-neutral-900 dark:text-white">
                              <span>{tc.name}</span>
                            </div>
                            <div className="text-[11px] text-neutral-400 dark:text-neutral-500">
                              Store-wide tax rate: {formatRate(tc.ratePercent)}
                            </div>
                          </div>
                        </div>
                      </td>

                      {/* Rate */}
                      <td className="py-4 px-5">
                        <span className="font-mono font-bold text-neutral-900 dark:text-white text-sm">
                          {formatRate(tc.ratePercent)}
                        </span>
                      </td>

                      {/* Status */}
                      <td className="py-4 px-5">
                        {isActive ? (
                          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800/80">
                            <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse" />
                            Active
                          </span>
                        ) : (
                          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400 border border-neutral-200 dark:border-neutral-700">
                            <span className="w-1.5 h-1.5 rounded-full bg-neutral-400" />
                            Inactive
                          </span>
                        )}
                      </td>

                      {/* Actions */}
                      <td className="py-4 px-5 text-right">
                        <div className="flex items-center justify-end gap-1.5">
                          {/* Activate / Deactivate button */}
                          {isActive ? (
                            <button
                              onClick={() => handleDeactivateClick(tc)}
                              title="Deactivate this tax class"
                              className="px-2.5 py-1.5 rounded-lg border border-amber-300 dark:border-amber-800/60 bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 hover:bg-amber-100 dark:hover:bg-amber-900/60 text-xs font-medium transition cursor-pointer"
                            >
                              Deactivate
                            </button>
                          ) : (
                            <button
                              onClick={() => handleActivateClick(tc)}
                              title="Activate this tax class"
                              className="px-2.5 py-1.5 rounded-lg border border-indigo-200 dark:border-indigo-800 bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 text-xs font-medium transition cursor-pointer"
                            >
                              Activate
                            </button>
                          )}

                          {/* Edit button */}
                          <button
                            onClick={() => handleOpenEdit(tc)}
                            title="Edit Tax Class"
                            className="p-1.5 rounded-lg text-neutral-500 dark:text-neutral-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition cursor-pointer"
                          >
                            <Edit2 className="w-4 h-4" />
                          </button>

                          {/* Delete button (Disabled if active) */}
                          {isActive ? (
                            <div className="relative group/tooltip inline-block">
                              <button
                                disabled
                                title="Deactivate this Tax Class before deleting it."
                                className="p-1.5 rounded-lg text-neutral-300 dark:text-neutral-700 cursor-not-allowed opacity-40"
                              >
                                <Trash2 className="w-4 h-4" />
                              </button>
                              <div className="absolute right-0 bottom-full mb-1 hidden group-hover/tooltip:block z-30 whitespace-nowrap px-2 py-1 rounded bg-neutral-900 text-white text-[10px] shadow-lg pointer-events-none">
                                Deactivate this Tax Class before deleting it.
                              </div>
                            </div>
                          ) : (
                            <button
                              onClick={() => handleDeleteClick(tc)}
                              title="Delete Tax Class"
                              className="p-1.5 rounded-lg text-neutral-500 dark:text-neutral-400 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition cursor-pointer"
                            >
                              <Trash2 className="w-4 h-4" />
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* ========================================================================= */}
      {/* ADD / EDIT TAX CLASS MODAL */}
      {/* ========================================================================= */}
      {isFormModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-neutral-950/70 backdrop-blur-xs">
          <div
            id="modal-tax-class-form"
            className="w-full max-w-md bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-150"
          >
            {/* Modal Header */}
            <div className="flex items-center justify-between px-6 py-4 border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50/80 dark:bg-neutral-800/40">
              <div className="flex items-center gap-2.5">
                <div className="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                  <Percent className="w-4 h-4" />
                </div>
                <div>
                  <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
                    {editingClass ? 'Edit Tax Class' : 'Add Tax Class'}
                  </h3>
                  <p className="text-[11px] text-neutral-500 dark:text-neutral-400">
                    Store-wide single percentage rate
                  </p>
                </div>
              </div>
              <button
                onClick={() => setIsFormModalOpen(false)}
                className="p-1.5 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-800 transition"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            {/* Modal Form */}
            <form onSubmit={handleSubmitForm} className="p-6 space-y-4">
              {/* API-style Error Banner */}
              {formError && (
                <div
                  id="form-error-region"
                  className="flex items-start gap-2.5 p-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-700 dark:text-rose-300 text-xs"
                >
                  <AlertCircle className="w-4 h-4 shrink-0 mt-0.5" />
                  <div>
                    <span className="font-semibold block">Validation Error</span>
                    <span>{formError}</span>
                  </div>
                </div>
              )}

              {/* 1. Tax Class Name */}
              <div>
                <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                  Tax Class Name <span className="text-rose-500">*</span>
                </label>
                <input
                  type="text"
                  required
                  placeholder="e.g. SST, GST, Service Charge"
                  value={formData.name}
                  onChange={e => {
                    setFormData({ ...formData, name: e.target.value });
                    if (formError) setFormError(null);
                  }}
                  className="w-full px-3.5 py-2.5 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white placeholder-neutral-400 focus:outline-hidden focus:ring-2 focus:ring-indigo-500"
                />
              </div>

              {/* 2. Tax Rate (%) */}
              <div>
                <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                  Tax Rate (%) <span className="text-rose-500">*</span>
                </label>
                <div className="relative">
                  <input
                    type="number"
                    step="any"
                    min="0"
                    max="100"
                    required
                    placeholder="8.0"
                    value={formData.ratePercent}
                    onChange={e => {
                      setFormData({ ...formData, ratePercent: e.target.value });
                      if (formError) setFormError(null);
                    }}
                    className="w-full px-3.5 py-2.5 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white font-mono placeholder-neutral-400 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 pr-8"
                  />
                  <span className="absolute right-3 top-2.5 text-xs font-bold text-neutral-400 pointer-events-none">
                    %
                  </span>
                </div>
                <p className="mt-1 text-[11px] text-neutral-500 dark:text-neutral-400">
                  Minimum 0, maximum 100. Up to four decimal places (e.g. 8.125%).
                </p>
              </div>

              {/* 3. Active Switch */}
              <div className="p-3.5 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-neutral-50/50 dark:bg-neutral-800/40 space-y-1.5">
                <div className="flex items-center justify-between">
                  <label htmlFor="tax-class-active-toggle" className="text-xs font-semibold text-neutral-900 dark:text-white cursor-pointer">
                    Set as Active Tax Class
                  </label>
                  <label className="relative inline-flex items-center cursor-pointer">
                    <input
                      id="tax-class-active-toggle"
                      type="checkbox"
                      checked={formData.isActive}
                      onChange={e => setFormData({ ...formData, isActive: e.target.checked })}
                      className="sr-only peer"
                    />
                    <div className="w-9 h-5 bg-neutral-300 dark:bg-neutral-700 peer-focus:outline-hidden rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-neutral-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"></div>
                  </label>
                </div>
                <p className="text-[11px] text-neutral-500 dark:text-neutral-400 leading-relaxed">
                  Only one Tax Class can be active. Activating this class will deactivate the current active class.
                </p>
              </div>

              {/* Modal Actions */}
              <div className="flex items-center justify-end gap-2.5 pt-3 border-t border-neutral-200 dark:border-neutral-800">
                <button
                  type="button"
                  onClick={() => setIsFormModalOpen(false)}
                  className="px-4 py-2 text-xs font-semibold rounded-xl border border-neutral-200 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition cursor-pointer"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={isSaving}
                  className="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white shadow-xs transition cursor-pointer"
                >
                  {isSaving && <Loader2 className="w-3.5 h-3.5 animate-spin" />}
                  {editingClass ? 'Save Changes' : 'Save Tax Class'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* ACTIVATION CONFIRMATION MODAL */}
      {/* ========================================================================= */}
      {activateConfirmTarget && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-neutral-950/70 backdrop-blur-xs">
          <div className="w-full max-w-md bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-2xl overflow-hidden p-6 space-y-4 animate-in fade-in zoom-in-95 duration-150">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-xl bg-indigo-100 dark:bg-indigo-950/80 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                <Power className="w-5 h-5" />
              </div>
              <div>
                <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
                  Activate {activateConfirmTarget.name}?
                </h3>
                <p className="text-xs text-neutral-500 dark:text-neutral-400">
                  Switching active store tax class
                </p>
              </div>
            </div>

            <p className="text-xs text-neutral-600 dark:text-neutral-300 leading-relaxed">
              <strong className="text-neutral-900 dark:text-white">{currentActiveClass?.name || 'Current active tax class'}</strong> will be deactivated. New carts and orders will use <strong className="text-neutral-900 dark:text-white">{formatRate(activateConfirmTarget.ratePercent)}</strong>. Historical orders will not change.
            </p>

            <div className="flex items-center justify-end gap-2.5 pt-2">
              <button
                onClick={() => setActivateConfirmTarget(null)}
                className="px-4 py-2 text-xs font-semibold rounded-xl border border-neutral-200 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition cursor-pointer"
              >
                Cancel
              </button>
              <button
                onClick={confirmActivate}
                className="px-4 py-2 text-xs font-semibold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs transition cursor-pointer"
              >
                Activate Tax Class
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* DEACTIVATION CONFIRMATION MODAL */}
      {/* ========================================================================= */}
      {deactivateConfirmTarget && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-neutral-950/70 backdrop-blur-xs">
          <div className="w-full max-w-md bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-2xl overflow-hidden p-6 space-y-4 animate-in fade-in zoom-in-95 duration-150">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-xl bg-amber-100 dark:bg-amber-950/80 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                <AlertTriangle className="w-5 h-5" />
              </div>
              <div>
                <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
                  Deactivate {deactivateConfirmTarget.name}?
                </h3>
                <p className="text-xs text-neutral-500 dark:text-neutral-400">
                  Zero store tax configuration
                </p>
              </div>
            </div>

            <p className="text-xs text-neutral-600 dark:text-neutral-300 leading-relaxed">
              New carts and orders will not be charged tax until another Tax Class is activated. Historical orders will not change.
            </p>

            <div className="flex items-center justify-end gap-2.5 pt-2">
              <button
                onClick={() => setDeactivateConfirmTarget(null)}
                className="px-4 py-2 text-xs font-semibold rounded-xl border border-neutral-200 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition cursor-pointer"
              >
                Cancel
              </button>
              <button
                onClick={confirmDeactivate}
                className="px-4 py-2 text-xs font-semibold rounded-xl bg-amber-600 hover:bg-amber-700 text-white shadow-xs transition cursor-pointer"
              >
                Deactivate Tax Class
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* DELETE CONFIRMATION MODAL */}
      {/* ========================================================================= */}
      {deleteConfirmTarget && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-neutral-950/70 backdrop-blur-xs">
          <div className="w-full max-w-md bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-2xl overflow-hidden p-6 space-y-4 animate-in fade-in zoom-in-95 duration-150">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-xl bg-rose-100 dark:bg-rose-950/80 text-rose-600 dark:text-rose-400 flex items-center justify-center shrink-0">
                <Trash2 className="w-5 h-5" />
              </div>
              <div>
                <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
                  Delete {deleteConfirmTarget.name}?
                </h3>
                <p className="text-xs text-neutral-500 dark:text-neutral-400">
                  Permanent removal of inactive tax class
                </p>
              </div>
            </div>

            <p className="text-xs text-neutral-600 dark:text-neutral-300 leading-relaxed">
              This removes the inactive Tax Class from future configuration. Historical order tax records will not change.
            </p>

            <div className="flex items-center justify-end gap-2.5 pt-2">
              <button
                onClick={() => setDeleteConfirmTarget(null)}
                className="px-4 py-2 text-xs font-semibold rounded-xl border border-neutral-200 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition cursor-pointer"
              >
                Cancel
              </button>
              <button
                onClick={confirmDelete}
                className="px-4 py-2 text-xs font-semibold rounded-xl bg-rose-600 hover:bg-rose-700 text-white shadow-xs transition cursor-pointer"
              >
                Delete Tax Class
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
