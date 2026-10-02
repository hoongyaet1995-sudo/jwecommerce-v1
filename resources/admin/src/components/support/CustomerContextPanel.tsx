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
  onClose: () => void;
}

export const CustomerContextPanel: React.FC<CustomerContextPanelProps> = ({
  conversation, onClose
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
      <div className="flex items-center justify-between"><h3 className="font-semibold">Customer details</h3><button onClick={onClose} aria-label="Close customer details" className="p-1.5 rounded-lg text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800">✕</button></div>
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

    </div>
  );
};
