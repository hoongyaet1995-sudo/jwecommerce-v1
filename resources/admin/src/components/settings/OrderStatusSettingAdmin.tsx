import React, { useState } from 'react';
import { useAdmin } from '../../context/AdminContext';
import { CustomOrderStatus } from '../../types/oscommerceSettings';
import {
  Clock,
  Plus,
  Edit2,
  Trash2,
  CheckCircle2,
  Bell,
  Mail,
  MessageSquare,
  ShieldCheck,
  Package,
  Layers,
  ArrowUpDown,
  Send,
  AlertCircle,
  Eye,
  Info,
  Check
} from 'lucide-react';

export const OrderStatusSettingAdmin: React.FC = () => {
  const {
    orderStatuses,
    saveOrderStatus,
    deleteOrderStatus,
    setDefaultOrderStatus,
    orders,
    setCurrentSection,
    addToast
  } = useAdmin();

  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingStatus, setEditingStatus] = useState<CustomOrderStatus | null>(null);
  const [testNotificationModal, setTestNotificationModal] = useState<CustomOrderStatus | null>(null);

  // Form State
  const [formData, setFormData] = useState<Partial<CustomOrderStatus>>({
    name: '',
    code: '',
    description: '',
    color: 'indigo',
    isDefault: false,
    isDefaultPaid: false,
    isDefaultCancelled: false,
    notifyCustomer: true,
    publicVisibility: true,
    inventoryAction: 'none',
    emailTemplate: 'Order Notification',
    whatsappTemplate: 'Hi {customer_name}, your order #{order_number} status has been updated to {status_name}.',
    sortOrder: 50
  });

  const handleOpenAdd = () => {
    setEditingStatus(null);
    setFormData({
      id: 'status_' + Date.now(),
      name: '',
      code: '',
      description: '',
      color: 'indigo',
      isDefault: false,
      isDefaultPaid: false,
      isDefaultCancelled: false,
      notifyCustomer: true,
      publicVisibility: true,
      inventoryAction: 'none',
      emailTemplate: 'Order Notification',
      whatsappTemplate: 'Hi {customer_name}, your order #{order_number} status is now {status_name}. Tracking: {tracking_url}',
      sortOrder: (orderStatuses.length + 1) * 10
    });
    setIsModalOpen(true);
  };

  const handleOpenEdit = (status: CustomOrderStatus) => {
    setEditingStatus(status);
    setFormData({ ...status });
    setIsModalOpen(true);
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!formData.name?.trim()) {
      addToast({ type: 'error', title: 'Name Required', message: 'Please enter a status title.' });
      return;
    }

    const code = formData.code?.trim() || formData.name.toLowerCase().replace(/[^a-z0-9]+/g, '_');
    const finalStatus: CustomOrderStatus = {
      id: formData.id || 'status_' + Date.now(),
      name: formData.name.trim(),
      code,
      description: formData.description || '',
      color: formData.color || 'indigo',
      isDefault: Boolean(formData.isDefault),
      isDefaultPaid: Boolean(formData.isDefaultPaid),
      isDefaultCancelled: Boolean(formData.isDefaultCancelled),
      notifyCustomer: Boolean(formData.notifyCustomer),
      publicVisibility: Boolean(formData.publicVisibility),
      inventoryAction: formData.inventoryAction || 'none',
      emailTemplate: formData.emailTemplate || 'Standard Notification',
      whatsappTemplate: formData.whatsappTemplate || '',
      sortOrder: Number(formData.sortOrder) || 10,
      ordersCount: editingStatus ? editingStatus.ordersCount : 0
    };

    saveOrderStatus(finalStatus);
    setIsModalOpen(false);
  };

  const colorBadgeClass = (color: CustomOrderStatus['color']) => {
    switch (color) {
      case 'emerald':
        return 'bg-emerald-100 text-emerald-800 border-emerald-300 dark:bg-emerald-950/80 dark:text-emerald-300 dark:border-emerald-800';
      case 'blue':
        return 'bg-blue-100 text-blue-800 border-blue-300 dark:bg-blue-950/80 dark:text-blue-300 dark:border-blue-800';
      case 'amber':
        return 'bg-amber-100 text-amber-800 border-amber-300 dark:bg-amber-950/80 dark:text-amber-300 dark:border-amber-800';
      case 'rose':
        return 'bg-rose-100 text-rose-800 border-rose-300 dark:bg-rose-950/80 dark:text-rose-300 dark:border-rose-800';
      case 'purple':
        return 'bg-purple-100 text-purple-800 border-purple-300 dark:bg-purple-950/80 dark:text-purple-300 dark:border-purple-800';
      case 'cyan':
        return 'bg-cyan-100 text-cyan-800 border-cyan-300 dark:bg-cyan-950/80 dark:text-cyan-300 dark:border-cyan-800';
      case 'neutral':
        return 'bg-neutral-200 text-neutral-800 border-neutral-300 dark:bg-neutral-800 dark:text-neutral-300 dark:border-neutral-700';
      default:
        return 'bg-indigo-100 text-indigo-800 border-indigo-300 dark:bg-indigo-950/80 dark:text-indigo-300 dark:border-indigo-800';
    }
  };

  return (
    <div className="space-y-6">
      {/* Header Banner */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-5 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
        <div>
          <div className="flex items-center gap-2">
            <span className="px-2.5 py-0.5 text-[10px] font-bold uppercase rounded-full bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 border border-indigo-500/30">
              osCommerce Core Architecture
            </span>
            <span className="text-xs text-neutral-500">Orders Status & Automated Notifications</span>
          </div>
          <h2 className="text-lg sm:text-xl font-bold text-neutral-900 dark:text-white tracking-tight mt-1">
            Orders Status Setting
          </h2>
          <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5 max-w-3xl">
            Configure order lifecycle state machines. Each status dynamically powers Order Fulfillment, determines customer tracking visibility, binds email/WhatsApp templates, and controls automated inventory reservation or restock.
          </p>
        </div>

        <button
          onClick={handleOpenAdd}
          className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs transition cursor-pointer self-start sm:self-auto"
        >
          <Plus className="w-4 h-4" />
          Add Custom Status
        </button>
      </div>

      {/* osCommerce Structural Architecture Overview Cards */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-3.5">
        <div className="p-4 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 space-y-1.5 shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-neutral-500">Total Configured</span>
            <Layers className="w-4 h-4 text-indigo-500" />
          </div>
          <div className="text-2xl font-bold text-neutral-900 dark:text-white">{orderStatuses.length}</div>
          <p className="text-[11px] text-neutral-500">Active status state machines</p>
        </div>

        <div className="p-4 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 space-y-1.5 shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-neutral-500">Default On Checkout</span>
            <CheckCircle2 className="w-4 h-4 text-emerald-500" />
          </div>
          <div className="text-sm font-bold text-neutral-900 dark:text-white truncate">
            {orderStatuses.find(s => s.isDefault)?.name || 'Pending Payment'}
          </div>
          <p className="text-[11px] text-neutral-500">Assigned on new orders</p>
        </div>

        <div className="p-4 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 space-y-1.5 shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-neutral-500">Customer Auto-Notify</span>
            <Bell className="w-4 h-4 text-amber-500" />
          </div>
          <div className="text-2xl font-bold text-neutral-900 dark:text-white">
            {orderStatuses.filter(s => s.notifyCustomer).length} <span className="text-xs font-normal text-neutral-400">statuses</span>
          </div>
          <p className="text-[11px] text-neutral-500">Auto triggers Email & WhatsApp</p>
        </div>

        <div className="p-4 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 space-y-1.5 shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-neutral-500">Inventory Triggers</span>
            <Package className="w-4 h-4 text-blue-500" />
          </div>
          <div className="text-xs font-bold text-neutral-900 dark:text-white mt-1">
            Reserve / Deduct / Restock
          </div>
          <p className="text-[11px] text-neutral-500">Automated stock management</p>
        </div>
      </div>

      {/* Orders Status Table */}
      <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 overflow-hidden shadow-xs">
        <div className="p-4 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between">
          <h3 className="text-xs font-bold text-neutral-900 dark:text-white uppercase tracking-wider">
            Order Statuses & Notification Triggers (osCommerce <code className="text-indigo-600 dark:text-indigo-400 font-mono">orders_status</code>)
          </h3>
          <span className="text-xs text-neutral-500">Sorted by pipeline progression</span>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead>
              <tr className="bg-neutral-50 dark:bg-neutral-800/60 text-neutral-500 dark:text-neutral-400 font-semibold border-b border-neutral-200 dark:border-neutral-800">
                <th className="py-3 px-4 w-12 text-center">Seq</th>
                <th className="py-3 px-4">Status Name & Code</th>
                <th className="py-3 px-4">Role & Flags</th>
                <th className="py-3 px-4">Inventory Action</th>
                <th className="py-3 px-4">Customer Notification</th>
                <th className="py-3 px-4 text-center">Live Orders</th>
                <th className="py-3 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-200 dark:divide-neutral-800">
              {orderStatuses.map((st) => (
                <tr key={st.id} className="hover:bg-neutral-50/70 dark:hover:bg-neutral-800/40 transition-colors">
                  <td className="py-3.5 px-4 text-center font-mono font-bold text-neutral-400 text-[11px]">
                    {st.sortOrder}
                  </td>
                  <td className="py-3.5 px-4">
                    <div className="flex items-center gap-2.5">
                      <span className={`inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold border ${colorBadgeClass(st.color)}`}>
                        {st.name}
                      </span>
                      <span className="text-[10px] font-mono text-neutral-400">({st.code})</span>
                    </div>
                    <p className="text-[11px] text-neutral-500 dark:text-neutral-400 mt-1 line-clamp-1 max-w-sm">
                      {st.description}
                    </p>
                  </td>
                  <td className="py-3.5 px-4">
                    <div className="flex flex-wrap gap-1">
                      {st.isDefault && (
                        <span className="px-2 py-0.5 text-[10px] font-bold rounded-md bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800">
                          ★ Default New
                        </span>
                      )}
                      {st.isDefaultPaid && (
                        <span className="px-2 py-0.5 text-[10px] font-bold rounded-md bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-300">
                          ✓ Default Paid
                        </span>
                      )}
                      {st.isDefaultCancelled && (
                        <span className="px-2 py-0.5 text-[10px] font-bold rounded-md bg-rose-100 dark:bg-rose-950 text-rose-700 dark:text-rose-300">
                          ✕ Default Void
                        </span>
                      )}
                      {st.publicVisibility ? (
                        <span className="px-1.5 py-0.5 text-[10px] text-neutral-600 dark:text-neutral-400 bg-neutral-100 dark:bg-neutral-800 rounded">
                          Public
                        </span>
                      ) : (
                        <span className="px-1.5 py-0.5 text-[10px] text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/50 rounded">
                          Internal Only
                        </span>
                      )}
                    </div>
                  </td>
                  <td className="py-3.5 px-4">
                    <span className={`inline-block px-2 py-0.5 text-[10px] font-semibold rounded-md ${
                      st.inventoryAction === 'deduct'
                        ? 'bg-blue-50 dark:bg-blue-950/50 text-blue-700 dark:text-blue-300'
                        : st.inventoryAction === 'reserve'
                        ? 'bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-300'
                        : st.inventoryAction === 'restock'
                        ? 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300'
                        : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-500'
                    }`}>
                      {st.inventoryAction === 'deduct' && 'Deduct Stock (WMS)'}
                      {st.inventoryAction === 'reserve' && 'Hold / Reserve ATP'}
                      {st.inventoryAction === 'restock' && 'Restock Available'}
                      {st.inventoryAction === 'none' && 'No Inventory Change'}
                    </span>
                  </td>
                  <td className="py-3.5 px-4">
                    <div className="space-y-1">
                      <div className="flex items-center gap-1.5">
                        {st.notifyCustomer ? (
                          <span className="inline-flex items-center gap-1 text-[11px] font-medium text-emerald-600 dark:text-emerald-400">
                            <CheckCircle2 className="w-3 h-3" /> Auto Notify Active
                          </span>
                        ) : (
                          <span className="inline-flex items-center gap-1 text-[11px] font-medium text-neutral-400">
                            Silent / No Auto
                          </span>
                        )}
                      </div>
                      <div className="flex items-center gap-1 text-[10px] text-neutral-500">
                        <Mail className="w-2.5 h-2.5" />
                        <span className="truncate max-w-[140px]">{st.emailTemplate}</span>
                      </div>
                    </div>
                  </td>
                  <td className="py-3.5 px-4 text-center">
                    <button
                      onClick={() => setCurrentSection('orders')}
                      className="inline-flex items-center justify-center px-2.5 py-1 rounded-lg bg-neutral-100 hover:bg-neutral-200 dark:bg-neutral-800 dark:hover:bg-neutral-700 text-xs font-bold text-neutral-700 dark:text-neutral-300 transition"
                      title="View Orders in this state"
                    >
                      {st.ordersCount || 0}
                    </button>
                  </td>
                  <td className="py-3.5 px-4 text-right">
                    <div className="flex items-center justify-end gap-1.5">
                      <button
                        onClick={() => setTestNotificationModal(st)}
                        title="Simulate Customer Notification"
                        className="p-1.5 text-neutral-500 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 rounded-lg transition"
                      >
                        <Send className="w-3.5 h-3.5" />
                      </button>
                      <button
                        onClick={() => handleOpenEdit(st)}
                        title="Edit Status Parameters"
                        className="p-1.5 text-neutral-500 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-950/50 rounded-lg transition"
                      >
                        <Edit2 className="w-3.5 h-3.5" />
                      </button>
                      {!st.isDefault && (
                        <button
                          onClick={() => deleteOrderStatus(st.id)}
                          title="Delete Status"
                          className="p-1.5 text-neutral-500 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/50 rounded-lg transition"
                        >
                          <Trash2 className="w-3.5 h-3.5" />
                        </button>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* Edit / Add Status Modal */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
          <div className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-3xl p-6 max-w-xl w-full shadow-2xl space-y-5 animate-in fade-in zoom-in-95 duration-150 my-8">
            <div className="flex items-center justify-between border-b border-neutral-200 dark:border-neutral-800 pb-3">
              <div>
                <h3 className="text-base font-bold text-neutral-900 dark:text-white">
                  {editingStatus ? 'Edit Order Status' : 'Create Custom Order Status'}
                </h3>
                <p className="text-xs text-neutral-500">osCommerce order lifecycle configuration & notification bindings</p>
              </div>
              <button
                onClick={() => setIsModalOpen(false)}
                className="p-2 rounded-xl text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 text-xs font-bold"
              >
                ✕
              </button>
            </div>

            <form onSubmit={handleSubmit} className="space-y-4">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                <div className="space-y-1">
                  <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Status Display Name *</label>
                  <input
                    type="text"
                    required
                    placeholder="e.g. Awaiting Bank Verification"
                    value={formData.name || ''}
                    onChange={e => setFormData({ ...formData, name: e.target.value })}
                    className="w-full px-3 py-2 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs text-neutral-900 dark:text-white"
                  />
                </div>

                <div className="space-y-1">
                  <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Internal Code Identifier</label>
                  <input
                    type="text"
                    placeholder="e.g. awaiting_bank_slip"
                    value={formData.code || ''}
                    onChange={e => setFormData({ ...formData, code: e.target.value })}
                    className="w-full px-3 py-2 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs text-neutral-900 dark:text-white font-mono"
                  />
                </div>
              </div>

              <div className="space-y-1">
                <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Description</label>
                <textarea
                  rows={2}
                  placeholder="Explain what this state indicates to fulfillment and warehouse operators..."
                  value={formData.description || ''}
                  onChange={e => setFormData({ ...formData, description: e.target.value })}
                  className="w-full px-3 py-2 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs text-neutral-900 dark:text-white"
                />
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div className="space-y-1">
                  <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Badge Color</label>
                  <select
                    value={formData.color || 'indigo'}
                    onChange={e => setFormData({ ...formData, color: e.target.value as CustomOrderStatus['color'] })}
                    className="w-full px-3 py-2 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs text-neutral-900 dark:text-white"
                  >
                    <option value="indigo">Indigo Blue</option>
                    <option value="emerald">Emerald Green</option>
                    <option value="amber">Amber Yellow</option>
                    <option value="blue">Royal Blue</option>
                    <option value="rose">Rose Red</option>
                    <option value="purple">Purple</option>
                    <option value="cyan">Cyan Teal</option>
                    <option value="neutral">Neutral Gray</option>
                  </select>
                </div>

                <div className="space-y-1">
                  <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Inventory Stock Action</label>
                  <select
                    value={formData.inventoryAction || 'none'}
                    onChange={e => setFormData({ ...formData, inventoryAction: e.target.value as CustomOrderStatus['inventoryAction'] })}
                    className="w-full px-3 py-2 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs text-neutral-900 dark:text-white"
                  >
                    <option value="none">None (No change)</option>
                    <option value="reserve">Reserve Stock</option>
                    <option value="deduct">Deduct Stock</option>
                    <option value="restock">Restock / Return</option>
                  </select>
                </div>

                <div className="space-y-1">
                  <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Sort Order</label>
                  <input
                    type="number"
                    value={formData.sortOrder || 10}
                    onChange={e => setFormData({ ...formData, sortOrder: Number(e.target.value) })}
                    className="w-full px-3 py-2 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs text-neutral-900 dark:text-white font-mono"
                  />
                </div>
              </div>

              {/* Notification Bindings */}
              <div className="p-3.5 rounded-2xl bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700 space-y-3">
                <div className="flex items-center justify-between">
                  <div>
                    <span className="text-xs font-bold text-neutral-900 dark:text-white">Auto-Notify Customer by Default</span>
                    <p className="text-[11px] text-neutral-500">Automatically checks "Notify Customer" when changing order to this status.</p>
                  </div>
                  <input
                    type="checkbox"
                    checked={Boolean(formData.notifyCustomer)}
                    onChange={e => setFormData({ ...formData, notifyCustomer: e.target.checked })}
                    className="w-4 h-4 rounded text-indigo-600 accent-indigo-600 cursor-pointer"
                  />
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
                  <div className="space-y-1">
                    <label className="text-[11px] font-semibold text-neutral-600 dark:text-neutral-400">Email Template</label>
                    <input
                      type="text"
                      placeholder="e.g. Shipment In-Transit Notice"
                      value={formData.emailTemplate || ''}
                      onChange={e => setFormData({ ...formData, emailTemplate: e.target.value })}
                      className="w-full px-3 py-1.5 rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-xs"
                    />
                  </div>

                  <div className="space-y-1">
                    <label className="text-[11px] font-semibold text-neutral-600 dark:text-neutral-400">WhatsApp Dispatch Template</label>
                    <input
                      type="text"
                      placeholder="e.g. Hi {customer_name}, your order #{order_number}..."
                      value={formData.whatsappTemplate || ''}
                      onChange={e => setFormData({ ...formData, whatsappTemplate: e.target.value })}
                      className="w-full px-3 py-1.5 rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-xs"
                    />
                  </div>
                </div>
              </div>

              {/* Special Flags */}
              <div className="flex flex-wrap gap-4 pt-1">
                <label className="flex items-center gap-2 text-xs text-neutral-700 dark:text-neutral-300 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={Boolean(formData.isDefault)}
                    onChange={e => setFormData({ ...formData, isDefault: e.target.checked })}
                    className="w-4 h-4 rounded text-indigo-600 accent-indigo-600 cursor-pointer"
                  />
                  <span>Default on New Orders</span>
                </label>

                <label className="flex items-center gap-2 text-xs text-neutral-700 dark:text-neutral-300 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={Boolean(formData.isDefaultPaid)}
                    onChange={e => setFormData({ ...formData, isDefaultPaid: e.target.checked })}
                    className="w-4 h-4 rounded text-indigo-600 accent-indigo-600 cursor-pointer"
                  />
                  <span>Default on Payment Success</span>
                </label>

                <label className="flex items-center gap-2 text-xs text-neutral-700 dark:text-neutral-300 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={Boolean(formData.publicVisibility)}
                    onChange={e => setFormData({ ...formData, publicVisibility: e.target.checked })}
                    className="w-4 h-4 rounded text-indigo-600 accent-indigo-600 cursor-pointer"
                  />
                  <span>Visible to Customer on Tracking Portal</span>
                </label>
              </div>

              <div className="flex items-center justify-end gap-2 pt-3 border-t border-neutral-200 dark:border-neutral-800">
                <button
                  type="button"
                  onClick={() => setIsModalOpen(false)}
                  className="px-4 py-2 rounded-xl border border-neutral-300 dark:border-neutral-700 text-xs font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs"
                >
                  Save Status Configuration
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Customer Notification Simulator Modal */}
      {testNotificationModal && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-3xl p-6 max-w-lg w-full shadow-2xl space-y-4 animate-in fade-in zoom-in-95 duration-150">
            <div className="flex items-center justify-between border-b border-neutral-200 dark:border-neutral-800 pb-3">
              <div className="flex items-center gap-2">
                <div className="p-2 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600">
                  <Send className="w-4 h-4" />
                </div>
                <div>
                  <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
                    Live Notification Preview ({testNotificationModal.name})
                  </h3>
                  <p className="text-[11px] text-neutral-500">How customer receives status updates via Email & WhatsApp</p>
                </div>
              </div>
              <button
                onClick={() => setTestNotificationModal(null)}
                className="text-neutral-400 hover:text-neutral-600 text-xs font-bold"
              >
                ✕
              </button>
            </div>

            <div className="space-y-3">
              <div className="p-4 rounded-2xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700 space-y-2">
                <div className="flex items-center gap-2 text-xs font-bold text-neutral-900 dark:text-white">
                  <Mail className="w-4 h-4 text-indigo-500" />
                  <span>Email Dispatch Preview:</span>
                </div>
                <div className="text-xs bg-white dark:bg-neutral-900 p-3 rounded-xl border border-neutral-200 dark:border-neutral-800 font-sans space-y-1">
                  <div className="text-[11px] text-neutral-500">Subject: Order #ORD-9842 Status Update: {testNotificationModal.name}</div>
                  <p className="text-neutral-800 dark:text-neutral-200 text-xs">
                    Dear Sarah Lim,<br />
                    Your order #ORD-9842 has been updated to <strong>{testNotificationModal.name}</strong>.
                  </p>
                  <p className="text-[11px] text-neutral-500">
                    Template: <code className="text-indigo-600">{testNotificationModal.emailTemplate}</code>
                  </p>
                </div>
              </div>

              {testNotificationModal.whatsappTemplate && (
                <div className="p-4 rounded-2xl bg-emerald-50/60 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 space-y-2">
                  <div className="flex items-center gap-2 text-xs font-bold text-emerald-900 dark:text-emerald-300">
                    <MessageSquare className="w-4 h-4 text-emerald-600" />
                    <span>WhatsApp Instant Dispatch:</span>
                  </div>
                  <div className="text-xs bg-white dark:bg-neutral-900 p-3 rounded-xl border border-emerald-200 dark:border-emerald-800 text-neutral-800 dark:text-neutral-200">
                    {testNotificationModal.whatsappTemplate
                      .replace('{customer_name}', 'Sarah Lim')
                      .replace('{order_number}', 'ORD-9842')
                      .replace('{status_name}', testNotificationModal.name)
                      .replace('{tracking_url}', 'https://aurora.shop/track/MY98421092')
                      .replace('{total_amount}', 'RM 289.00')
                      .replace('{points_earned}', '289')}
                  </div>
                </div>
              )}
            </div>

            <button
              onClick={() => {
                addToast({
                  type: 'success',
                  title: 'Test Notification Dispatched',
                  message: `Sent test alert for "${testNotificationModal.name}" via Email & WhatsApp webhook.`
                });
                setTestNotificationModal(null);
              }}
              className="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition"
            >
              Send Test Dispatch to Admin Phone & Email
            </button>
          </div>
        </div>
      )}
    </div>
  );
};
