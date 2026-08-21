import React, { useState, useEffect } from 'react';
import { BankAccountDetails } from '../../types/payment';
import { PaymentLogo } from '../common/PaymentLogo';
import {
  X,
  Building,
  QrCode,
  CheckCircle2,
  Upload,
  AlertCircle
} from 'lucide-react';

interface BankAccountModalProps {
  isOpen: boolean;
  onClose: () => void;
  account: BankAccountDetails | null;
  onSave: (account: BankAccountDetails) => void;
}

const POPULAR_MALAYSIAN_BANKS = [
  { name: 'Maybank (Malayan Banking Berhad)', code: 'MBBEMYKL' },
  { name: 'CIMB Bank Berhad', code: 'CIBBMYKL' },
  { name: 'Public Bank Berhad', code: 'PBBEMYKL' },
  { name: 'RHB Bank Berhad', code: 'RHBBMYKL' },
  { name: 'Hong Leong Bank Berhad', code: 'HLBBMYKL' },
  { name: 'AmBank (M) Berhad', code: 'ARBKMYKL' },
  { name: 'Bank Islam Malaysia Berhad', code: 'BIMBMYKL' },
  { name: 'Bank Muamalat Malaysia Berhad', code: 'BMMBMYKL' },
  { name: 'Alliance Bank Malaysia Berhad', code: 'MACBMYKL' },
  { name: 'Affin Bank Berhad', code: 'ABBBMYKL' },
  { name: 'OCBC Bank (Malaysia) Berhad', code: 'OCBCMYKL' },
  { name: 'UOB Bank (United Overseas Bank)', code: 'UOVBMYKL' },
  { name: 'Standard Chartered Bank Malaysia', code: 'SCBLMYKL' },
  { name: 'HSBC Bank Malaysia Berhad', code: 'HSBCMYKL' },
];

export const BankAccountModal: React.FC<BankAccountModalProps> = ({
  isOpen,
  onClose,
  account,
  onSave
}) => {
  const [formData, setFormData] = useState<BankAccountDetails>({
    id: '',
    bankName: POPULAR_MALAYSIAN_BANKS[0].name,
    bankCode: POPULAR_MALAYSIAN_BANKS[0].code,
    accountHolderName: '',
    accountNumber: '',
    branchName: '',
    swiftCode: POPULAR_MALAYSIAN_BANKS[0].code,
    duitNowId: '',
    duitNowQrUrl: '',
    isPrimary: false,
    isActive: true,
  });

  useEffect(() => {
    if (account) {
      setFormData(account);
    } else {
      setFormData({
        id: 'bank-' + Date.now(),
        bankName: POPULAR_MALAYSIAN_BANKS[0].name,
        bankCode: POPULAR_MALAYSIAN_BANKS[0].code,
        accountHolderName: 'AURORA COMMERCE SDN BHD',
        accountNumber: '',
        branchName: '',
        swiftCode: POPULAR_MALAYSIAN_BANKS[0].code,
        duitNowId: '202301048291',
        duitNowQrUrl: '',
        isPrimary: false,
        isActive: true,
      });
    }
  }, [account, isOpen]);

  if (!isOpen) return null;

  const handleBankSelect = (selectedName: string) => {
    const matched = POPULAR_MALAYSIAN_BANKS.find(b => b.name === selectedName);
    setFormData({
      ...formData,
      bankName: selectedName,
      bankCode: matched?.code || '',
      swiftCode: matched?.code || '',
    });
  };

  const handleGenerateDuitNowQr = () => {
    const rawData = `DuitNowQR_${formData.bankCode}_${formData.accountNumber.replace(/\s+/g, '')}`;
    const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(rawData)}`;
    setFormData({
      ...formData,
      duitNowQrUrl: qrUrl
    });
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!formData.accountNumber.trim()) return;
    onSave(formData);
    onClose();
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs overflow-y-auto">
      <div className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-2xl w-full max-w-xl shadow-2xl overflow-hidden my-8 animate-in fade-in zoom-in-95 duration-150">
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50/50 dark:bg-neutral-950/50">
          <div className="flex items-center gap-3">
            <PaymentLogo
              type={
                formData.bankName.toLowerCase().includes('maybank') ? 'maybank' :
                formData.bankName.toLowerCase().includes('cimb') ? 'cimb' :
                formData.bankName.toLowerCase().includes('public') ? 'public_bank' :
                formData.bankName.toLowerCase().includes('rhb') ? 'rhb' :
                formData.bankName.toLowerCase().includes('hong leong') ? 'hong_leong' :
                'bank_transfer'
              }
              size="md"
            />
            <div>
              <h2 className="text-base font-bold text-neutral-900 dark:text-white">
                {account ? 'Edit Bank Account' : 'Add Malaysian Bank Account'}
              </h2>
              <p className="text-xs text-neutral-500">Provide bank information displayed to shoppers at checkout</p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-2 rounded-lg text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition cursor-pointer"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Form Body */}
        <form onSubmit={handleSubmit} className="p-6 space-y-4 max-h-[75vh] overflow-y-auto">
          {/* Bank Selection */}
          <div>
            <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Bank Name</label>
            <select
              value={formData.bankName}
              onChange={e => handleBankSelect(e.target.value)}
              className="w-full mt-1 px-3.5 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-semibold"
            >
              {POPULAR_MALAYSIAN_BANKS.map(b => (
                <option key={b.name} value={b.name}>
                  {b.name}
                </option>
              ))}
            </select>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Account Number *</label>
              <input
                type="text"
                required
                value={formData.accountNumber}
                onChange={e => setFormData({ ...formData, accountNumber: e.target.value })}
                placeholder="e.g. 5140 8291 0029"
                className="w-full mt-1 px-3.5 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-mono font-bold"
              />
            </div>
            <div>
              <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Account Holder Name *</label>
              <input
                type="text"
                required
                value={formData.accountHolderName}
                onChange={e => setFormData({ ...formData, accountHolderName: e.target.value })}
                placeholder="e.g. AURORA COMMERCE SDN BHD"
                className="w-full mt-1 px-3.5 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs"
              />
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Branch Name (Optional)</label>
              <input
                type="text"
                value={formData.branchName || ''}
                onChange={e => setFormData({ ...formData, branchName: e.target.value })}
                placeholder="e.g. Mid Valley City Branch"
                className="w-full mt-1 px-3.5 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs"
              />
            </div>
            <div>
              <label className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">SWIFT / BIC Code</label>
              <input
                type="text"
                value={formData.swiftCode || ''}
                onChange={e => setFormData({ ...formData, swiftCode: e.target.value })}
                placeholder="e.g. MBBEMYKL"
                className="w-full mt-1 px-3.5 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-mono"
              />
            </div>
          </div>

          {/* DuitNow QR Section */}
          <div className="p-4 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-800/40 space-y-3">
            <div className="flex items-center justify-between">
              <label className="text-xs font-bold text-neutral-900 dark:text-white flex items-center gap-1.5">
                <QrCode className="w-4 h-4 text-pink-600" />
                DuitNow QR & ID Configuration
              </label>
              <button
                type="button"
                onClick={handleGenerateDuitNowQr}
                className="text-[11px] font-semibold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 cursor-pointer"
              >
                Auto-Generate QR
              </button>
            </div>

            <div>
              <label className="text-[11px] text-neutral-600 dark:text-neutral-400">DuitNow Registered ID (SSM Number / Mobile / NRIC)</label>
              <input
                type="text"
                value={formData.duitNowId || ''}
                onChange={e => setFormData({ ...formData, duitNowId: e.target.value })}
                placeholder="e.g. 202301048291 (SSM) or 012-3456789"
                className="w-full mt-1 px-3 py-1.5 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs"
              />
            </div>

            {formData.duitNowQrUrl && (
              <div className="flex items-center gap-4 pt-2">
                <img
                  src={formData.duitNowQrUrl}
                  alt="DuitNow QR"
                  referrerPolicy="no-referrer"
                  className="w-20 h-20 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white p-1"
                />
                <div className="text-[11px] text-neutral-500 space-y-1">
                  <span className="font-semibold text-neutral-800 dark:text-neutral-200">DuitNow QR Preview</span>
                  <p>Customers can scan this QR code using Maybank MAE, TNG eWallet, CIMB OCTO, GrabPay, or any bank app.</p>
                </div>
              </div>
            )}
          </div>

          {/* Primary & Active Toggles */}
          <div className="grid grid-cols-2 gap-4">
            <label className="flex items-center gap-2 p-3 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-800/40 cursor-pointer">
              <input
                type="checkbox"
                checked={formData.isPrimary}
                onChange={e => setFormData({ ...formData, isPrimary: e.target.checked })}
                className="w-4 h-4 rounded text-indigo-600 accent-indigo-600"
              />
              <span className="text-xs font-semibold text-neutral-800 dark:text-neutral-200">Set as Primary Account</span>
            </label>

            <label className="flex items-center gap-2 p-3 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-800/40 cursor-pointer">
              <input
                type="checkbox"
                checked={formData.isActive}
                onChange={e => setFormData({ ...formData, isActive: e.target.checked })}
                className="w-4 h-4 rounded text-indigo-600 accent-indigo-600"
              />
              <span className="text-xs font-semibold text-neutral-800 dark:text-neutral-200">Active for Checkout</span>
            </label>
          </div>

          {/* Footer Actions */}
          <div className="flex items-center justify-end gap-3 pt-4 border-t border-neutral-200 dark:border-neutral-800">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 rounded-xl text-xs font-semibold text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition cursor-pointer"
            >
              Cancel
            </button>
            <button
              type="submit"
              className="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition cursor-pointer shadow-sm"
            >
              Save Bank Account
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};
