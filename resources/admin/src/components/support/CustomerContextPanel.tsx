import React, { useState } from 'react';
import { useAdmin } from '../../context/AdminContext';
import { SupportConversation } from '../../types/support';
import {
  User,
  Phone,
  Mail,
  MapPin,
  Calendar,
  DollarSign,
  ShoppingBag,
  Sparkles,
  AlertTriangle,
  FileText,
  Plus,
  Send,
  ExternalLink,
  ShieldCheck,
  Tag,
  CheckCircle2,
  TrendingUp,
  CreditCard,
  Truck,
  HeartHandshake
} from 'lucide-react';

interface CustomerContextPanelProps {
  conversation: SupportConversation;
}

export const CustomerContextPanel: React.FC<CustomerContextPanelProps> = ({
  conversation
}) => {
  const { addStaffNote, addToast } = useAdmin();
  const [newNote, setNewNote] = useState('');

  const handleAddNote = (e: React.FormEvent) => {
    e.preventDefault();
    if (!newNote.trim()) return;
    addStaffNote(conversation.id, newNote);
    setNewNote('');
  };

  const handleApplyVoucher = () => {
    addToast({
      type: 'success',
      title: 'Voucher Code Generated',
      message: 'RM 20 VIP voucher (CODE: AURORA20VIP) sent to customer inbox.'
    });
  };

  const handleGenerateInvoice = () => {
    addToast({
      type: 'info',
      title: 'SST Invoice Generated',
      message: 'Official SST 8% Tax Invoice PDF dispatched.'
    });
  };

  const handleExportTranscript = () => {
    addToast({
      type: 'info',
      title: 'Transcript Exported',
      message: 'Conversation audit log exported to CSV / PDF.'
    });
  };

  const { customer, aiSummary, internalNotes } = conversation;

  return (
    <div id="customer-context-panel" className="h-full flex flex-col p-4 space-y-4 text-xs overflow-y-auto">
      {/* 1. Customer Profile Card */}
      <div className="p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700/80 space-y-3">
        <div className="flex items-center gap-3">
          <img
            src={customer.avatar}
            alt={customer.name}
            className="w-12 h-12 rounded-full object-cover ring-2 ring-indigo-500/20"
          />
          <div className="flex-1 min-w-0">
            <h3 className="text-sm font-semibold text-neutral-900 dark:text-neutral-100 truncate">
              {customer.name}
            </h3>
            <div className="flex items-center gap-1.5 mt-0.5">
              <span className="px-2 py-0.5 text-[10px] font-bold rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                {customer.qualification || 'Verified Buyer'}
              </span>
              <span className="text-[11px] text-neutral-400">
                {customer.tags?.[0] || 'Direct Web'}
              </span>
            </div>
          </div>
        </div>

        {/* Customer Metrics */}
        <div className="grid grid-cols-2 gap-2 pt-2 border-t border-neutral-200 dark:border-neutral-700/60 text-[11px]">
          <div className="p-2 rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800">
            <span className="text-neutral-500 block text-[10px]">Lifetime Value</span>
            <span className="font-bold text-neutral-900 dark:text-neutral-100 text-xs text-indigo-600 dark:text-indigo-400">
              RM {customer.lifetimeValue.toFixed(2)}
            </span>
          </div>
          <div className="p-2 rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800">
            <span className="text-neutral-500 block text-[10px]">Total Orders</span>
            <span className="font-bold text-neutral-900 dark:text-neutral-100 text-xs">
              {customer.orderCount} Orders
            </span>
          </div>
        </div>

        {/* Contact info list */}
        <div className="space-y-1.5 pt-1 text-[11px] text-neutral-600 dark:text-neutral-300">
          {customer.phone && (
            <div className="flex items-center gap-2">
              <Phone className="w-3.5 h-3.5 text-neutral-400" />
              <span className="font-mono">{customer.phone}</span>
            </div>
          )}
          {customer.email && (
            <div className="flex items-center gap-2">
              <Mail className="w-3.5 h-3.5 text-neutral-400" />
              <span className="truncate">{customer.email}</span>
            </div>
          )}
          {customer.city && (
            <div className="flex items-center gap-2">
              <MapPin className="w-3.5 h-3.5 text-neutral-400" />
              <span>{customer.city}, {customer.country || 'Malaysia'}</span>
            </div>
          )}
          {customer.joinedDate && (
            <div className="flex items-center gap-2">
              <Calendar className="w-3.5 h-3.5 text-neutral-400" />
              <span>Customer since {customer.joinedDate}</span>
            </div>
          )}
        </div>
      </div>

      {/* 2. AI Lead & Sentiment Intelligence Card */}
      <div className="p-3.5 rounded-xl bg-gradient-to-br from-indigo-50/70 to-purple-50/70 dark:from-indigo-950/40 dark:to-purple-950/40 border border-indigo-200 dark:border-indigo-800/60 space-y-2.5">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-1.5 text-indigo-900 dark:text-indigo-200 font-semibold">
            <Sparkles className="w-3.5 h-3.5 text-indigo-600" />
            <span>AI Lead Intelligence</span>
          </div>
          <span className="px-2 py-0.2 text-[10px] font-bold rounded-full bg-indigo-200 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-300">
            {(customer.qualification || customer.leadStage || 'HIGH INTENT').toUpperCase()}
          </span>
        </div>

        <div className="space-y-1.5 text-[11px]">
          <div>
            <span className="text-neutral-500 block text-[10px]">Intent Classification:</span>
            <span className="font-medium text-neutral-800 dark:text-neutral-200">
              {aiSummary?.intent || 'General Support Inquiry'}
            </span>
          </div>

          <div className="flex items-center justify-between pt-1">
            <span className="text-neutral-500 text-[10px]">Sentiment Score:</span>
            <span className={`font-semibold capitalize ${
              aiSummary?.sentiment === 'positive' ? 'text-emerald-600' : aiSummary?.sentiment === 'frustrated' ? 'text-red-500' : 'text-neutral-700 dark:text-neutral-300'
            }`}>
              {aiSummary?.sentiment || 'Neutral'}
            </span>
          </div>

          <div className="flex items-center justify-between">
            <span className="text-neutral-500 text-[10px]">Recommended Action:</span>
            <span className="text-indigo-600 dark:text-indigo-400 font-medium text-right truncate ml-2">
              {aiSummary?.suggestedAction || 'Review message history and assist customer'}
            </span>
          </div>
        </div>
      </div>

      {/* 3. Recent Order & Shipping Snapshot */}
      <div className="p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700/80 space-y-2.5">
        <div className="flex items-center justify-between font-semibold text-neutral-900 dark:text-neutral-100">
          <div className="flex items-center gap-1.5">
            <ShoppingBag className="w-3.5 h-3.5 text-indigo-600" />
            <span>Recent Commerce Order</span>
          </div>
          <span className="text-[10px] text-indigo-600 font-normal">AUR-MY-89210</span>
        </div>

        <div className="p-2 rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 space-y-1 text-[11px]">
          <div className="flex items-center justify-between">
            <span className="font-semibold text-neutral-800 dark:text-neutral-200">280GSM Tee (M, Charcoal)</span>
            <span className="font-bold text-neutral-900 dark:text-neutral-100">RM 89.00</span>
          </div>
          <div className="flex items-center justify-between text-neutral-500 text-[10px]">
            <span>Pos Malaysia Express</span>
            <span className="font-mono text-indigo-600">ER920194821MY</span>
          </div>
          <div className="pt-1 flex items-center justify-between text-[10px]">
            <span className="text-emerald-600 font-medium flex items-center gap-1">
              <Truck className="w-3 h-3" /> In Transit (ETA Tomorrow)
            </span>
            <span className="text-neutral-400">SST 8% Included</span>
          </div>
        </div>
      </div>

      {/* 4. Internal Teammate Notes */}
      <div className="p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700/80 space-y-3">
        <div className="flex items-center justify-between font-semibold text-neutral-900 dark:text-neutral-100">
          <div className="flex items-center gap-1.5">
            <FileText className="w-3.5 h-3.5 text-indigo-600" />
            <span>Private Staff Notes ({internalNotes.length})</span>
          </div>
        </div>

        {/* Existing notes */}
        <div className="space-y-2 max-h-36 overflow-y-auto">
          {internalNotes.length === 0 ? (
            <p className="text-[11px] text-neutral-400 italic">No internal notes yet.</p>
          ) : (
            internalNotes.map((note) => (
              <div
                key={note.id}
                className="p-2 rounded-lg bg-amber-50/70 dark:bg-amber-950/30 border border-amber-200/60 dark:border-amber-800/50 space-y-1"
              >
                <div className="flex items-center justify-between text-[10px] text-neutral-500">
                  <span className="font-semibold text-neutral-700 dark:text-neutral-300">{note.authorName}</span>
                  <span>{note.timestamp}</span>
                </div>
                <p className="text-[11px] text-neutral-800 dark:text-neutral-200">{note.content}</p>
              </div>
            ))
          )}
        </div>

        {/* Add Note Form */}
        <form onSubmit={handleAddNote} className="space-y-2 pt-1 border-t border-neutral-200 dark:border-neutral-700/60">
          <input
            type="text"
            placeholder="Add internal teammate note..."
            value={newNote}
            onChange={(e) => setNewNote(e.target.value)}
            className="w-full px-2.5 py-1.5 text-xs rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-neutral-100 placeholder-neutral-400 focus:outline-none focus:ring-1 focus:ring-indigo-500"
          />
          <button
            type="submit"
            disabled={!newNote.trim()}
            className="w-full flex items-center justify-center gap-1 py-1 text-xs font-medium bg-neutral-900 hover:bg-neutral-800 disabled:opacity-40 text-white rounded-lg transition-colors dark:bg-neutral-100 dark:text-neutral-900"
          >
            <Plus className="w-3.5 h-3.5" />
            <span>Save Private Note</span>
          </button>
        </form>
      </div>

      {/* 5. Quick Support Actions */}
      <div className="space-y-2 pt-2 border-t border-neutral-200 dark:border-neutral-800">
        <span className="text-[11px] font-semibold text-neutral-500 block uppercase tracking-wider">
          Quick Workflows
        </span>
        <div className="grid grid-cols-1 gap-1.5">
          <button
            onClick={handleApplyVoucher}
            className="w-full flex items-center justify-between px-3 py-2 rounded-lg bg-neutral-100 hover:bg-neutral-200 dark:bg-neutral-800 dark:hover:bg-neutral-700 text-neutral-800 dark:text-neutral-200 transition-colors text-left"
          >
            <div className="flex items-center gap-2">
              <Tag className="w-3.5 h-3.5 text-indigo-500" />
              <span>Apply RM20 Loyalty Voucher</span>
            </div>
            <span className="text-[10px] text-neutral-400 font-mono">1-Click</span>
          </button>

          <button
            onClick={handleGenerateInvoice}
            className="w-full flex items-center justify-between px-3 py-2 rounded-lg bg-neutral-100 hover:bg-neutral-200 dark:bg-neutral-800 dark:hover:bg-neutral-700 text-neutral-800 dark:text-neutral-200 transition-colors text-left"
          >
            <div className="flex items-center gap-2">
              <CreditCard className="w-3.5 h-3.5 text-blue-500" />
              <span>Generate SST Tax Invoice</span>
            </div>
            <span className="text-[10px] text-neutral-400 font-mono">PDF</span>
          </button>

          <button
            onClick={handleExportTranscript}
            className="w-full flex items-center justify-between px-3 py-2 rounded-lg bg-neutral-100 hover:bg-neutral-200 dark:bg-neutral-800 dark:hover:bg-neutral-700 text-neutral-800 dark:text-neutral-200 transition-colors text-left"
          >
            <div className="flex items-center gap-2">
              <FileText className="w-3.5 h-3.5 text-emerald-500" />
              <span>Export Audit Transcript</span>
            </div>
            <span className="text-[10px] text-neutral-400 font-mono">CSV</span>
          </button>
        </div>
      </div>
    </div>
  );
};
