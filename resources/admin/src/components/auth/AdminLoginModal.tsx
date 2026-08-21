import React, { useState } from 'react';
import { useAdmin } from '../../context/AdminContext';
import { ShieldCheck, Lock, Mail, KeyRound, Sparkles, X, Check, ArrowRight } from 'lucide-react';
import { AdminRole } from '../../types';

export const AdminLoginModal: React.FC = () => {
  const { isLoginModalOpen, setIsLoginModalOpen, login, user } = useAdmin();
  const [email, setEmail] = useState('alexander.s@auroracommerce.io');
  const [password, setPassword] = useState('••••••••••••');
  const [role, setRole] = useState<AdminRole>('super_admin');
  const [rememberMe, setRememberMe] = useState(true);
  const [step2FA, setStep2FA] = useState(false);
  const [twoFACode, setTwoFACode] = useState('849201');

  if (!isLoginModalOpen && user) return null;

  const handleInitialSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!email) return;
    setStep2FA(true);
  };

  const handle2FASubmit = (e: React.FormEvent) => {
    e.preventDefault();
    login(email, role);
    setStep2FA(false);
  };

  const quickLoginAs = (quickEmail: string, quickRole: AdminRole) => {
    setEmail(quickEmail);
    setRole(quickRole);
    login(quickEmail, quickRole);
    setStep2FA(false);
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-md animate-in fade-in">
      <div
        id="admin-login-modal"
        className="w-full max-w-md bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-3xl shadow-2xl overflow-hidden p-6 sm:p-8 relative"
      >
        {user && (
          <button
            onClick={() => setIsLoginModalOpen(false)}
            className="absolute top-4 right-4 p-2 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 rounded-xl"
          >
            <X className="w-5 h-5" />
          </button>
        )}

        {/* Branding header */}
        <div className="text-center mb-6">
          <div className="w-12 h-12 rounded-2xl bg-gradient-to-tr from-cyan-600 via-indigo-600 to-violet-600 mx-auto flex items-center justify-center shadow-lg shadow-indigo-500/25 mb-3 text-white">
            <ShieldCheck className="w-6 h-6" />
          </div>
          <h2 className="text-xl font-bold text-neutral-900 dark:text-white tracking-tight">
            {step2FA ? 'Two-Factor Authentication' : 'Admin Portal Login'}
          </h2>
          <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-1">
            {step2FA
              ? 'Enter the 6-digit TOTP verification code from your Authenticator app.'
              : 'Sign in to access Universal Ecommerce administration.'}
          </p>
        </div>

        {!step2FA ? (
          <form onSubmit={handleInitialSubmit} className="space-y-4">
            <div>
              <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1.5">
                Admin Work Email
              </label>
              <div className="relative">
                <Mail className="w-4 h-4 text-neutral-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                <input
                  type="email"
                  required
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="admin@auroracommerce.io"
                  className="w-full pl-10 pr-3.5 py-2.5 rounded-xl border bg-neutral-50 dark:bg-neutral-800/80 border-neutral-200 dark:border-neutral-700 text-xs text-neutral-900 dark:text-white focus:outline-hidden focus:ring-2 focus:ring-indigo-500"
                />
              </div>
            </div>

            <div>
              <div className="flex items-center justify-between mb-1.5">
                <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                  Password
                </label>
                <span className="text-[11px] text-indigo-600 dark:text-indigo-400 hover:underline cursor-pointer">
                  Forgot?
                </span>
              </div>
              <div className="relative">
                <Lock className="w-4 h-4 text-neutral-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                <input
                  type="password"
                  required
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  className="w-full pl-10 pr-3.5 py-2.5 rounded-xl border bg-neutral-50 dark:bg-neutral-800/80 border-neutral-200 dark:border-neutral-700 text-xs text-neutral-900 dark:text-white focus:outline-hidden focus:ring-2 focus:ring-indigo-500"
                />
              </div>
            </div>

            <div>
              <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1.5">
                Target Role
              </label>
              <select
                value={role}
                onChange={(e) => setRole(e.target.value as AdminRole)}
                className="w-full px-3.5 py-2.5 rounded-xl border bg-neutral-50 dark:bg-neutral-800/80 border-neutral-200 dark:border-neutral-700 text-xs text-neutral-900 dark:text-white focus:outline-hidden focus:ring-2 focus:ring-indigo-500"
              >
                <option value="super_admin">Super Administrator (Full Privileges)</option>
                <option value="catalog_manager">Catalog & Navigation Manager</option>
                <option value="order_specialist">Order Fulfillment Specialist</option>
                <option value="developer">Developer (API & Specs Access)</option>
              </select>
            </div>

            <div className="flex items-center justify-between py-1">
              <label className="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  checked={rememberMe}
                  onChange={(e) => setRememberMe(e.target.checked)}
                  className="rounded border-neutral-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4"
                />
                <span className="text-xs text-neutral-600 dark:text-neutral-400">Remember this workstation</span>
              </label>
              <span className="text-[10px] uppercase font-bold text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                <ShieldCheck className="w-3.5 h-3.5" /> 2FA Required
              </span>
            </div>

            <button
              type="submit"
              className="w-full py-2.5 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-lg shadow-indigo-500/20 transition-all flex items-center justify-center gap-2"
            >
              <span>Continue to 2FA</span>
              <ArrowRight className="w-3.5 h-3.5" />
            </button>
          </form>
        ) : (
          <form onSubmit={handle2FASubmit} className="space-y-4">
            <div>
              <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1.5 text-center">
                Security Code
              </label>
              <div className="relative">
                <KeyRound className="w-4 h-4 text-neutral-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                <input
                  type="text"
                  maxLength={6}
                  required
                  value={twoFACode}
                  onChange={(e) => setTwoFACode(e.target.value)}
                  className="w-full tracking-widest text-center text-lg font-mono font-bold py-2.5 rounded-xl border bg-neutral-50 dark:bg-neutral-800/80 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white focus:outline-hidden focus:ring-2 focus:ring-indigo-500"
                />
              </div>
              <p className="text-[11px] text-neutral-400 text-center mt-1.5">
                Demo code autofilled: <span className="font-mono font-bold text-indigo-400">849201</span>
              </p>
            </div>

            <div className="flex gap-2">
              <button
                type="button"
                onClick={() => setStep2FA(false)}
                className="w-1/3 py-2.5 px-3 rounded-xl border border-neutral-300 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300 text-xs font-medium hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors"
              >
                Back
              </button>
              <button
                type="submit"
                className="w-2/3 py-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-lg shadow-emerald-500/20 transition-all flex items-center justify-center gap-1.5"
              >
                <Check className="w-4 h-4" />
                <span>Verify & Enter Admin</span>
              </button>
            </div>
          </form>
        )}

        {/* Demo Fast Login Switchers */}
        <div className="mt-6 pt-5 border-t border-neutral-100 dark:border-neutral-800">
          <p className="text-[11px] font-semibold uppercase text-neutral-400 mb-2 text-center">
            One-Click Demo Personas
          </p>
          <div className="grid grid-cols-2 gap-2">
            <button
              onClick={() => quickLoginAs('alexander.s@auroracommerce.io', 'super_admin')}
              className="p-2 rounded-xl border border-neutral-200 dark:border-neutral-700/80 hover:bg-neutral-50 dark:hover:bg-neutral-800 text-left transition-colors"
            >
              <div className="text-xs font-bold text-neutral-900 dark:text-white">Alexander S.</div>
              <div className="text-[10px] text-indigo-500 font-medium">Super Admin</div>
            </button>
            <button
              onClick={() => quickLoginAs('maya.lin@auroracommerce.io', 'catalog_manager')}
              className="p-2 rounded-xl border border-neutral-200 dark:border-neutral-700/80 hover:bg-neutral-50 dark:hover:bg-neutral-800 text-left transition-colors"
            >
              <div className="text-xs font-bold text-neutral-900 dark:text-white">Maya Lin</div>
              <div className="text-[10px] text-cyan-500 font-medium">Catalog & Menu Mgr</div>
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};
