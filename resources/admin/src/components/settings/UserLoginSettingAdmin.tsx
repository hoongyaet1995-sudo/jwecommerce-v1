import React, { useState } from 'react';
import { useAdmin } from '../../context/AdminContext';
import { StaffUser, StaffPermissionKey } from '../../types';
import { STAFF_PERMISSION_OPTIONS } from '../../data/mockData';
import {
  UserPlus,
  Search,
  Shield,
  Key,
  MoreVertical,
  Edit2,
  Trash2,
  Lock,
  Eye,
  EyeOff,
  Check,
  RotateCcw,
  Sparkles,
  ArrowLeft,
  Copy,
  CheckCircle2,
  AlertCircle,
  Users,
  Settings,
  ShieldCheck,
  Layers,
  X
} from 'lucide-react';

export const UserLoginSettingAdmin: React.FC = () => {
  const { staffUsers, saveStaffUser, deleteStaffUser, toggleStaffStatus, resetStaffPassword, addToast } = useAdmin();

  // Mode: 'list' | 'create' | 'edit'
  const [viewMode, setViewMode] = useState<'list' | 'create' | 'edit'>('list');
  const [editingStaff, setEditingStaff] = useState<StaffUser | null>(null);

  // Search & Filter in list mode
  const [searchTerm, setSearchTerm] = useState('');
  const [positionFilter, setPositionFilter] = useState('all');

  // Form State
  const [formUsername, setFormUsername] = useState('');
  const [formFullName, setFormFullName] = useState('');
  const [formEmail, setFormEmail] = useState('');
  const [formPhone, setFormPhone] = useState('');
  const [formPassword, setFormPassword] = useState('');
  const [formPosition, setFormPosition] = useState('');
  const [formPermissions, setFormPermissions] = useState<StaffPermissionKey[]>([]);
  const [formIsActive, setFormIsActive] = useState(true);
  const [formNotes, setFormNotes] = useState('');
  const [showPassword, setShowPassword] = useState(false);

  // Quick Action Dropdown State
  const [activeMenuId, setActiveMenuId] = useState<string | null>(null);

  // Password Reset Modal State
  const [resetModalStaff, setResetModalStaff] = useState<StaffUser | null>(null);
  const [newPasswordValue, setNewPasswordValue] = useState('');
  const [copiedKey, setCopiedKey] = useState(false);

  // Delete Confirmation Modal
  const [deletingStaff, setDeletingStaff] = useState<StaffUser | null>(null);

  // Filter staff
  const filteredStaff = staffUsers.filter(staff => {
    const matchSearch =
      staff.username.toLowerCase().includes(searchTerm.toLowerCase()) ||
      (staff.fullName && staff.fullName.toLowerCase().includes(searchTerm.toLowerCase())) ||
      (staff.email && staff.email.toLowerCase().includes(searchTerm.toLowerCase())) ||
      staff.position.toLowerCase().includes(searchTerm.toLowerCase());
    const matchPosition = positionFilter === 'all' || staff.position.toLowerCase() === positionFilter.toLowerCase();
    return matchSearch && matchPosition;
  });

  const uniquePositions: string[] = Array.from(new Set(staffUsers.map(s => s.position)));

  // Preset Handlers
  const handlePresetSelect = (preset: 'super_admin' | 'sales' | 'accountant' | 'catalog' | 'support' | 'all' | 'none') => {
    switch (preset) {
      case 'all':
      case 'super_admin':
        setFormPermissions(STAFF_PERMISSION_OPTIONS.map(p => p.key));
        break;
      case 'none':
        setFormPermissions([]);
        break;
      case 'sales':
        setFormPermissions(['dashboard', 'customers', 'orders', 'category', 'products', 'promotions', 'Sale Report', 'Marketplace']);
        break;
      case 'accountant':
        setFormPermissions(['dashboard', 'configuration', 'customers', 'orders', 'category', 'products', 'promotions', 'Rewards', 'Layout', 'Sale Report', 'Module Integration', 'Tax', 'lhdn']);
        break;
      case 'catalog':
        setFormPermissions(['category', 'products', 'promotions', 'Layout']);
        break;
      case 'support':
        setFormPermissions(['customers', 'orders', 'dashboard']);
        break;
    }
  };

  const togglePermission = (key: StaffPermissionKey) => {
    setFormPermissions(prev =>
      prev.includes(key) ? prev.filter(k => k !== key) : [...prev, key]
    );
  };

  const generateRandomPassword = () => {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%&*';
    let pass = '';
    for (let i = 0; i < 12; i++) {
      pass += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    setFormPassword(pass);
    setShowPassword(true);
    addToast({ type: 'info', title: 'Password Generated', message: 'A secure 12-character random password has been generated.' });
  };

  const openCreateView = () => {
    setEditingStaff(null);
    setFormUsername('');
    setFormFullName('');
    setFormEmail('');
    setFormPhone('');
    setFormPassword('');
    setFormPosition('Sales Team');
    setFormPermissions(['customers', 'orders', 'products', 'promotions']);
    setFormIsActive(true);
    setFormNotes('');
    setShowPassword(false);
    setViewMode('create');
  };

  const openEditView = (staff: StaffUser) => {
    setEditingStaff(staff);
    setFormUsername(staff.username);
    setFormFullName(staff.fullName || '');
    setFormEmail(staff.email || '');
    setFormPhone(staff.phone || '');
    setFormPassword(staff.password || '');
    setFormPosition(staff.position);
    setFormPermissions([...staff.permissions]);
    setFormIsActive(staff.isActive);
    setFormNotes(staff.notes || '');
    setShowPassword(false);
    setActiveMenuId(null);
    setViewMode('edit');
  };

  const handleSaveForm = (e: React.FormEvent) => {
    e.preventDefault();
    if (!formUsername.trim()) {
      addToast({ type: 'error', title: 'Validation Failed', message: 'Username cannot be empty.' });
      return;
    }
    if (!formPosition.trim()) {
      addToast({ type: 'error', title: 'Validation Failed', message: 'Position name cannot be empty.' });
      return;
    }

    const staffId = editingStaff ? editingStaff.id : `staff-${Date.now()}`;
    const newStaff: StaffUser = {
      id: staffId,
      username: formUsername.trim(),
      fullName: formFullName.trim() || undefined,
      email: formEmail.trim() || undefined,
      phone: formPhone.trim() || undefined,
      position: formPosition.trim(),
      password: formPassword.trim() || editingStaff?.password || 'Aurora@2026!',
      permissions: formPermissions,
      isActive: formIsActive,
      lastLoginAt: editingStaff?.lastLoginAt || 'Never logged in',
      createdAt: editingStaff?.createdAt || new Date().toISOString().split('T')[0],
      avatarColor: editingStaff?.avatarColor || (formPosition.toLowerCase().includes('admin') ? 'bg-indigo-600' : 'bg-emerald-600'),
      notes: formNotes.trim() || undefined
    };

    saveStaffUser(newStaff);
    setViewMode('list');
  };

  const openResetPasswordModal = (staff: StaffUser) => {
    setResetModalStaff(staff);
    setNewPasswordValue('');
    setCopiedKey(false);
    setActiveMenuId(null);
  };

  const handleConfirmPasswordReset = () => {
    if (!resetModalStaff) return;
    if (!newPasswordValue.trim() || newPasswordValue.length < 6) {
      addToast({ type: 'error', title: 'Password Too Short', message: 'Please provide at least 6 characters for the new password.' });
      return;
    }
    resetStaffPassword(resetModalStaff.id, newPasswordValue.trim());
    setResetModalStaff(null);
  };

  return (
    <div className="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">
      {/* ---------------------------------------------------- */}
      {/* VIEW MODE: EDIT / CREATE STAFF (Screenshot 2 Match) */}
      {/* ---------------------------------------------------- */}
      {viewMode !== 'list' ? (
        <div className="space-y-6 animate-fadeIn">
          {/* Header Bar */}
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div className="flex items-center gap-3">
              <button
                id="btn-back-to-users-list"
                type="button"
                onClick={() => setViewMode('list')}
                className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-semibold text-neutral-700 dark:text-neutral-200 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition shadow-xs cursor-pointer"
              >
                <ArrowLeft className="w-4 h-4 text-neutral-500" />
                Back
              </button>
              <div>
                <h1 className="text-xl font-bold text-neutral-900 dark:text-white tracking-tight">
                  {viewMode === 'edit' ? 'Edit User Login' : 'Add New User Login'}
                </h1>
                <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
                  {viewMode === 'edit'
                    ? 'You may edit login username, password and edit permissions.'
                    : 'Create credentials and assign custom authorization roles and permissions for your staff.'}
                </p>
              </div>
            </div>

            {/* Top preset badge */}
            <div className="flex items-center gap-2">
              <span className="text-xs font-medium text-neutral-500">Quick Presets:</span>
              <button
                type="button"
                onClick={() => handlePresetSelect('super_admin')}
                className="px-2.5 py-1 text-[11px] font-medium rounded-md bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 hover:bg-indigo-100 cursor-pointer"
              >
                Admin (All)
              </button>
              <button
                type="button"
                onClick={() => handlePresetSelect('sales')}
                className="px-2.5 py-1 text-[11px] font-medium rounded-md bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 hover:bg-emerald-100 cursor-pointer"
              >
                Sales Role
              </button>
              <button
                type="button"
                onClick={() => handlePresetSelect('accountant')}
                className="px-2.5 py-1 text-[11px] font-medium rounded-md bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800 hover:bg-amber-100 cursor-pointer"
              >
                Accountant
              </button>
            </div>
          </div>

          {/* Form Card */}
          <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-6 sm:p-8 shadow-sm">
            <form onSubmit={handleSaveForm} className="space-y-6 max-w-3xl">
              {/* Username Input */}
              <div className="space-y-1.5">
                <label className="block text-xs font-semibold text-neutral-800 dark:text-neutral-200">
                  Username <span className="text-red-500">*</span>
                </label>
                <input
                  id="input-staff-username"
                  type="text"
                  required
                  placeholder="e.g. Airene, ayu, edtan, sales_lead"
                  value={formUsername}
                  onChange={e => setFormUsername(e.target.value)}
                  className="w-full px-3.5 py-2.5 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-sm text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all shadow-2xs"
                />
              </div>

              {/* Password Input */}
              <div className="space-y-1.5">
                <div className="flex items-center justify-between">
                  <label className="block text-xs font-semibold text-neutral-800 dark:text-neutral-200">
                    {viewMode === 'edit' ? 'New Password' : 'Password'}{' '}
                    {viewMode === 'create' && <span className="text-red-500">*</span>}
                  </label>
                  <button
                    type="button"
                    onClick={generateRandomPassword}
                    className="inline-flex items-center gap-1 text-[11px] font-medium text-indigo-600 dark:text-indigo-400 hover:underline cursor-pointer"
                  >
                    <Sparkles className="w-3 h-3" />
                    Auto-Generate Strong Password
                  </button>
                </div>
                <div className="relative">
                  <input
                    id="input-staff-password"
                    type={showPassword ? 'text' : 'password'}
                    placeholder={viewMode === 'edit' ? 'Leave blank to keep existing password' : 'Enter login password'}
                    value={formPassword}
                    onChange={e => setFormPassword(e.target.value)}
                    className="w-full px-3.5 py-2.5 pr-10 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-sm text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all shadow-2xs"
                  />
                  <button
                    type="button"
                    onClick={() => setShowPassword(!showPassword)}
                    className="absolute right-3 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 cursor-pointer"
                  >
                    {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                  </button>
                </div>
                {formPassword && (
                  <div className="flex items-center gap-2 pt-1 text-[11px] text-neutral-500">
                    <span className="font-medium">Password Length:</span> {formPassword.length} characters
                    {formPassword.length >= 8 && <span className="text-emerald-600 font-semibold">• Strong</span>}
                  </div>
                )}
              </div>

              {/* Position Name Input */}
              <div className="space-y-1.5">
                <div className="flex items-center gap-2">
                  <label className="block text-xs font-semibold text-neutral-800 dark:text-neutral-200">
                    Position Name <span className="text-red-500">*</span>
                  </label>
                  <span className="px-2 py-0.5 text-[10px] font-medium rounded-full bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-300 border border-neutral-200 dark:border-neutral-700">
                    Define your position name.
                  </span>
                </div>
                <input
                  id="input-staff-position"
                  type="text"
                  required
                  placeholder="e.g. Airene sale, Admin, sales team, ACCOUNT, Store Manager"
                  value={formPosition}
                  onChange={e => setFormPosition(e.target.value)}
                  className="w-full px-3.5 py-2.5 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-sm text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all shadow-2xs"
                />
                {/* Suggestions */}
                <div className="flex flex-wrap items-center gap-1.5 pt-1">
                  <span className="text-[11px] text-neutral-400">Quick fill:</span>
                  {['Admin', 'Airene sale', 'sales team', 'ACCOUNT', 'Store Manager', 'Customer Support', 'Warehouse Lead'].map(role => (
                    <button
                      key={role}
                      type="button"
                      onClick={() => setFormPosition(role)}
                      className="px-2 py-0.5 rounded text-[10px] font-medium bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-300 hover:bg-indigo-100 hover:text-indigo-700 dark:hover:bg-indigo-950 dark:hover:text-indigo-300 border border-neutral-200 dark:border-neutral-700 transition cursor-pointer"
                    >
                      {role}
                    </button>
                  ))}
                </div>
              </div>

              {/* Optional Contact Fields */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-neutral-100 dark:border-neutral-800">
                <div className="space-y-1.5">
                  <label className="block text-xs font-medium text-neutral-700 dark:text-neutral-300">
                    Staff Full Name (Optional)
                  </label>
                  <input
                    type="text"
                    placeholder="e.g. Airene Wong"
                    value={formFullName}
                    onChange={e => setFormFullName(e.target.value)}
                    className="w-full px-3.5 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-indigo-500"
                  />
                </div>
                <div className="space-y-1.5">
                  <label className="block text-xs font-medium text-neutral-700 dark:text-neutral-300">
                    Staff Email / Phone (Optional)
                  </label>
                  <input
                    type="text"
                    placeholder="e.g. airene@aurora.com or +60 12-345 6789"
                    value={formEmail}
                    onChange={e => setFormEmail(e.target.value)}
                    className="w-full px-3.5 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-indigo-500"
                  />
                </div>
              </div>

              {/* Permission Control Section (Exact Match to Screenshot 2) */}
              <div className="space-y-3 pt-4 border-t border-neutral-200 dark:border-neutral-800">
                <div className="flex items-center justify-between">
                  <div>
                    <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
                      Permission Control
                    </h3>
                    <p className="text-xs text-neutral-500 dark:text-neutral-400">
                      Selected ({formPermissions.length} of {STAFF_PERMISSION_OPTIONS.length}) permissions assigned to this user.
                    </p>
                  </div>
                  <div className="flex items-center gap-2">
                    <button
                      type="button"
                      onClick={() => handlePresetSelect('all')}
                      className="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline cursor-pointer"
                    >
                      Select All
                    </button>
                    <span className="text-neutral-300 dark:text-neutral-700">|</span>
                    <button
                      type="button"
                      onClick={() => handlePresetSelect('none')}
                      className="text-xs font-semibold text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200 cursor-pointer"
                    >
                      Clear All
                    </button>
                  </div>
                </div>

                {/* Checkbox List */}
                <div className="space-y-2.5 pt-2">
                  {STAFF_PERMISSION_OPTIONS.map(opt => {
                    const isChecked = formPermissions.includes(opt.key);
                    return (
                      <label
                        key={opt.key}
                        className={`flex items-start gap-3 p-2.5 rounded-xl border transition-all cursor-pointer select-none ${
                          isChecked
                            ? 'bg-indigo-50/50 dark:bg-indigo-950/20 border-indigo-200 dark:border-indigo-800/60'
                            : 'bg-neutral-50/50 dark:bg-neutral-800/30 border-neutral-200/70 dark:border-neutral-800 hover:bg-neutral-100/60 dark:hover:bg-neutral-800/60'
                        }`}
                      >
                        <input
                          type="checkbox"
                          checked={isChecked}
                          onChange={() => togglePermission(opt.key)}
                          className="mt-0.5 w-4 h-4 rounded border-neutral-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer accent-indigo-600"
                        />
                        <div className="flex-1 min-w-0">
                          <div className="flex items-center justify-between">
                            <span className={`text-xs font-semibold ${isChecked ? 'text-indigo-950 dark:text-indigo-200' : 'text-neutral-800 dark:text-neutral-200'}`}>
                              {opt.label}
                            </span>
                            <span className="text-[10px] font-mono px-1.5 py-0.5 rounded bg-neutral-100 dark:bg-neutral-800 text-neutral-500">
                              {opt.key}
                            </span>
                          </div>
                          <p className="text-[11px] text-neutral-500 dark:text-neutral-400 mt-0.5">
                            {opt.description}
                          </p>
                        </div>
                      </label>
                    );
                  })}
                </div>
              </div>

              {/* Status Toggle */}
              <div className="flex items-center justify-between p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700/60">
                <div>
                  <div className="text-xs font-bold text-neutral-900 dark:text-white">Account Status</div>
                  <div className="text-[11px] text-neutral-500">Enable or disable login access for this staff user immediately.</div>
                </div>
                <button
                  type="button"
                  onClick={() => setFormIsActive(!formIsActive)}
                  className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors cursor-pointer ${
                    formIsActive ? 'bg-emerald-500' : 'bg-neutral-300 dark:bg-neutral-700'
                  }`}
                >
                  <span
                    className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                      formIsActive ? 'translate-x-6' : 'translate-x-1'
                    }`}
                  />
                </button>
              </div>

              {/* Action Buttons (Screenshot 2 Match: Update, Cancel) */}
              <div className="flex items-center gap-3 pt-4 border-t border-neutral-200 dark:border-neutral-800">
                <button
                  id="btn-submit-staff-user"
                  type="submit"
                  className="px-6 py-2.5 rounded-xl bg-indigo-800 hover:bg-indigo-900 text-white font-semibold text-xs transition shadow-md shadow-indigo-700/20 cursor-pointer"
                >
                  {viewMode === 'edit' ? 'Update' : 'Create User'}
                </button>
                <button
                  type="button"
                  onClick={() => setViewMode('list')}
                  className="px-5 py-2.5 rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-700 font-semibold text-xs transition cursor-pointer"
                >
                  Cancel
                </button>
              </div>
            </form>
          </div>
        </div>
      ) : (
        /* ---------------------------------------------------- */
        /* VIEW MODE: STAFF LIST TABLE (Screenshot 1 Match) */
        /* ---------------------------------------------------- */
        <div className="space-y-6 animate-fadeIn">
          {/* Header Title Section */}
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <h1 className="text-2xl font-bold text-neutral-900 dark:text-white tracking-tight">
                User Login Setting
              </h1>
              <p className="text-xs sm:text-sm text-neutral-500 dark:text-neutral-400 mt-1">
                You can create unlimited admin user login and assign the user role.
              </p>
            </div>

            {/* New User + Button (Exact deep blue / indigo styling) */}
            <button
              id="btn-new-staff-user"
              type="button"
              onClick={openCreateView}
              className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-indigo-900 hover:bg-indigo-950 dark:bg-indigo-600 dark:hover:bg-indigo-700 text-white text-xs sm:text-sm font-semibold shadow-md shadow-indigo-900/20 transition-all cursor-pointer"
            >
              <UserPlus className="w-4 h-4" />
              <span>New User +</span>
            </button>
          </div>

          {/* Quick Metrics Bar */}
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div className="p-3.5 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200/80 dark:border-neutral-800 shadow-xs">
              <div className="text-[11px] font-semibold text-neutral-400 uppercase tracking-wider">Total Staff Logins</div>
              <div className="text-xl font-bold text-neutral-900 dark:text-white mt-1">{staffUsers.length}</div>
            </div>
            <div className="p-3.5 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200/80 dark:border-neutral-800 shadow-xs">
              <div className="text-[11px] font-semibold text-neutral-400 uppercase tracking-wider">Active Staff</div>
              <div className="text-xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">
                {staffUsers.filter(s => s.isActive).length}
              </div>
            </div>
            <div className="p-3.5 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200/80 dark:border-neutral-800 shadow-xs">
              <div className="text-[11px] font-semibold text-neutral-400 uppercase tracking-wider">Position Roles</div>
              <div className="text-xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">
                {uniquePositions.length}
              </div>
            </div>
            <div className="p-3.5 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200/80 dark:border-neutral-800 shadow-xs">
              <div className="text-[11px] font-semibold text-neutral-400 uppercase tracking-wider">Security Engine</div>
              <div className="text-xs font-bold text-cyan-600 dark:text-cyan-400 mt-2 flex items-center gap-1.5">
                <ShieldCheck className="w-4 h-4" />
                RBAC Level 3
              </div>
            </div>
          </div>

          {/* Search & Filter Toolbar */}
          <div className="p-3.5 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 flex flex-col sm:flex-row items-center justify-between gap-3 shadow-xs">
            <div className="relative w-full sm:w-80">
              <Search className="w-4 h-4 text-neutral-400 absolute left-3 top-1/2 -translate-y-1/2" />
              <input
                type="text"
                placeholder="Search staff by username, position..."
                value={searchTerm}
                onChange={e => setSearchTerm(e.target.value)}
                className="w-full pl-9 pr-3.5 py-1.5 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800 text-xs text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
              />
            </div>

            <div className="flex items-center gap-2 w-full sm:w-auto overflow-x-auto pb-1 sm:pb-0">
              <span className="text-xs font-semibold text-neutral-500 whitespace-nowrap">Filter Position:</span>
              <button
                type="button"
                onClick={() => setPositionFilter('all')}
                className={`px-3 py-1 rounded-lg text-xs font-medium transition cursor-pointer whitespace-nowrap ${
                  positionFilter === 'all'
                    ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900 font-semibold'
                    : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400 hover:bg-neutral-200'
                }`}
              >
                All ({staffUsers.length})
              </button>
              {uniquePositions.map(pos => (
                <button
                  key={pos}
                  type="button"
                  onClick={() => setPositionFilter(pos)}
                  className={`px-3 py-1 rounded-lg text-xs font-medium transition cursor-pointer whitespace-nowrap ${
                    positionFilter.toLowerCase() === pos.toLowerCase()
                      ? 'bg-emerald-600 text-white font-semibold'
                      : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400 hover:bg-neutral-200'
                  }`}
                >
                  {pos}
                </button>
              ))}
            </div>
          </div>

          {/* User Logins Table (Screenshot 1 Representation) */}
          <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-sm overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full text-left border-collapse">
                <thead>
                  <tr className="border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50/70 dark:bg-neutral-800/40 text-neutral-600 dark:text-neutral-400 text-xs font-bold uppercase tracking-wider">
                    <th className="py-3.5 px-5 w-44">Username</th>
                    <th className="py-3.5 px-5 w-36">Position</th>
                    <th className="py-3.5 px-5">Permission</th>
                    <th className="py-3.5 px-5 text-right w-24">Action</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800/60 text-xs">
                  {filteredStaff.length === 0 ? (
                    <tr>
                      <td colSpan={4} className="py-12 text-center text-neutral-400">
                        <Users className="w-8 h-8 mx-auto mb-2 opacity-40" />
                        <p className="font-semibold">No staff users found</p>
                        <p className="text-xs mt-1">Try adjusting your search criteria or create a new user.</p>
                      </td>
                    </tr>
                  ) : (
                    filteredStaff.map(staff => (
                      <tr
                        key={staff.id}
                        className={`hover:bg-neutral-50/80 dark:hover:bg-neutral-800/30 transition-colors ${
                          !staff.isActive ? 'opacity-60 bg-neutral-50/30' : ''
                        }`}
                      >
                        {/* Username */}
                        <td className="py-4 px-5">
                          <div className="flex items-center gap-3">
                            <div
                              className={`w-8 h-8 rounded-full ${
                                staff.avatarColor || 'bg-indigo-600'
                              } text-white flex items-center justify-center font-bold text-xs shadow-xs flex-shrink-0`}
                            >
                              {(staff.username || staff.id || 'AD').slice(0, 2).toUpperCase()}
                            </div>
                            <div className="min-w-0">
                              <div className="font-bold text-neutral-900 dark:text-white flex items-center gap-1.5">
                                <span>{staff.username}</span>
                                {!staff.isActive && (
                                  <span className="text-[10px] font-semibold px-1.5 py-0.2 rounded bg-neutral-200 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400">
                                    Disabled
                                  </span>
                                )}
                              </div>
                              {staff.fullName && (
                                <div className="text-[11px] text-neutral-400 truncate">
                                  {staff.fullName}
                                </div>
                              )}
                              {staff.lastLoginAt && (
                                <div className="text-[10px] text-neutral-400/80 font-mono">
                                  Last: {staff.lastLoginAt}
                                </div>
                              )}
                            </div>
                          </div>
                        </td>

                        {/* Position Badge (Emerald Green pill matching screenshot 1) */}
                        <td className="py-4 px-5">
                          <span className="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300/60 dark:border-emerald-800/60 shadow-2xs">
                            {staff.position}
                          </span>
                        </td>

                        {/* Permission Chips (Wrapped pills matching screenshot 1) */}
                        <td className="py-4 px-5">
                          <div className="flex flex-wrap items-center gap-1.5 max-w-2xl">
                            {(staff.permissions || []).map(perm => (
                              <span
                                key={perm}
                                className="inline-block px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 border border-neutral-200/80 dark:border-neutral-700/60"
                              >
                                {perm}
                              </span>
                            ))}
                          </div>
                        </td>

                        {/* Action Menu (...) */}
                        <td className="py-4 px-5 text-right relative">
                          <div className="inline-block text-left">
                            <button
                              id={`action-btn-staff-${staff.id}`}
                              type="button"
                              onClick={() =>
                                setActiveMenuId(activeMenuId === staff.id ? null : staff.id)
                              }
                              className="p-1.5 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200 transition cursor-pointer"
                            >
                              <MoreVertical className="w-4 h-4" />
                            </button>

                            {/* Dropdown Menu */}
                            {activeMenuId === staff.id && (
                              <>
                                <div
                                  className="fixed inset-0 z-20"
                                  onClick={() => setActiveMenuId(null)}
                                />
                                <div className="absolute right-4 top-10 z-30 w-44 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xl py-1.5 text-left animate-in fade-in zoom-in-95 duration-100">
                                  <button
                                    type="button"
                                    onClick={() => openEditView(staff)}
                                    className="w-full flex items-center gap-2.5 px-3.5 py-2 text-xs font-medium text-neutral-700 dark:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition cursor-pointer"
                                  >
                                    <Edit2 className="w-3.5 h-3.5 text-neutral-400" />
                                    Edit Credentials
                                  </button>

                                  <button
                                    type="button"
                                    onClick={() => openResetPasswordModal(staff)}
                                    className="w-full flex items-center gap-2.5 px-3.5 py-2 text-xs font-medium text-neutral-700 dark:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition cursor-pointer"
                                  >
                                    <Key className="w-3.5 h-3.5 text-amber-500" />
                                    Reset Password
                                  </button>

                                  <button
                                    type="button"
                                    onClick={() => {
                                      toggleStaffStatus(staff.id);
                                      setActiveMenuId(null);
                                    }}
                                    className="w-full flex items-center gap-2.5 px-3.5 py-2 text-xs font-medium text-neutral-700 dark:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition cursor-pointer"
                                  >
                                    <RotateCcw className="w-3.5 h-3.5 text-indigo-400" />
                                    {staff.isActive ? 'Disable Account' : 'Activate Account'}
                                  </button>

                                  <div className="my-1 border-t border-neutral-100 dark:border-neutral-800" />

                                  <button
                                    type="button"
                                    disabled={staff.username === 'admin'}
                                    onClick={() => {
                                      setDeletingStaff(staff);
                                      setActiveMenuId(null);
                                    }}
                                    className={`w-full flex items-center gap-2.5 px-3.5 py-2 text-xs font-medium transition cursor-pointer ${
                                      staff.username === 'admin'
                                        ? 'text-neutral-300 dark:text-neutral-600 cursor-not-allowed'
                                        : 'text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/40'
                                    }`}
                                  >
                                    <Trash2 className="w-3.5 h-3.5" />
                                    Delete User
                                  </button>
                                </div>
                              </>
                            )}
                          </div>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* ---------------------------------------------------- */}
      {/* QUICK RESET PASSWORD MODAL */}
      {/* ---------------------------------------------------- */}
      {resetModalStaff && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
          <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 max-w-md w-full p-6 shadow-2xl space-y-4 animate-in fade-in zoom-in-95">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-500 flex items-center justify-center">
                  <Key className="w-5 h-5" />
                </div>
                <div>
                  <h3 className="text-base font-bold text-neutral-900 dark:text-white">
                    Reset Password
                  </h3>
                  <p className="text-xs text-neutral-500">
                    For staff user: <span className="font-semibold text-neutral-800 dark:text-neutral-200">"{resetModalStaff.username}"</span>
                  </p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setResetModalStaff(null)}
                className="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 cursor-pointer"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <div className="space-y-3 pt-2">
              <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                New Password
              </label>
              <div className="relative">
                <input
                  type="text"
                  placeholder="Enter new password (min 6 chars)"
                  value={newPasswordValue}
                  onChange={e => setNewPasswordValue(e.target.value)}
                  className="w-full px-3.5 py-2.5 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-sm font-mono text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-amber-500"
                />
              </div>

              <div className="flex items-center justify-between">
                <button
                  type="button"
                  onClick={() => {
                    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%';
                    let pass = '';
                    for (let i = 0; i < 10; i++) {
                      pass += chars.charAt(Math.floor(Math.random() * chars.length));
                    }
                    setNewPasswordValue(pass);
                  }}
                  className="text-xs font-medium text-amber-600 dark:text-amber-400 hover:underline cursor-pointer"
                >
                  Generate random password
                </button>

                {newPasswordValue && (
                  <button
                    type="button"
                    onClick={() => {
                      navigator.clipboard.writeText(newPasswordValue);
                      setCopiedKey(true);
                      setTimeout(() => setCopiedKey(false), 2000);
                    }}
                    className="inline-flex items-center gap-1 text-xs text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 cursor-pointer"
                  >
                    {copiedKey ? <CheckCircle2 className="w-3.5 h-3.5 text-emerald-500" /> : <Copy className="w-3.5 h-3.5" />}
                    {copiedKey ? 'Copied!' : 'Copy to clipboard'}
                  </button>
                )}
              </div>
            </div>

            <div className="flex items-center justify-end gap-3 pt-4 border-t border-neutral-100 dark:border-neutral-800">
              <button
                type="button"
                onClick={() => setResetModalStaff(null)}
                className="px-4 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 text-xs font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800 cursor-pointer"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={handleConfirmPasswordReset}
                className="px-4 py-2 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold shadow-sm cursor-pointer"
              >
                Apply New Password
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ---------------------------------------------------- */}
      {/* DELETE CONFIRMATION MODAL */}
      {/* ---------------------------------------------------- */}
      {deletingStaff && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
          <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 max-w-md w-full p-6 shadow-2xl space-y-4 animate-in fade-in zoom-in-95">
            <div className="flex items-center gap-3 text-red-600">
              <div className="w-10 h-10 rounded-xl bg-red-100 dark:bg-red-950/50 flex items-center justify-center flex-shrink-0">
                <AlertCircle className="w-5 h-5" />
              </div>
              <div>
                <h3 className="text-base font-bold text-neutral-900 dark:text-white">
                  Delete Staff Account
                </h3>
                <p className="text-xs text-neutral-500">This operation is irreversible.</p>
              </div>
            </div>

            <p className="text-xs text-neutral-600 dark:text-neutral-300 leading-relaxed">
              Are you sure you want to permanently delete the staff user login for{' '}
              <strong className="text-neutral-900 dark:text-white">"{deletingStaff.username}"</strong> ({deletingStaff.position})? They will immediately lose all administrative dashboard access.
            </p>

            <div className="flex items-center justify-end gap-3 pt-3 border-t border-neutral-100 dark:border-neutral-800">
              <button
                type="button"
                onClick={() => setDeletingStaff(null)}
                className="px-4 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 text-xs font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 cursor-pointer"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={() => {
                  deleteStaffUser(deletingStaff.id);
                  setDeletingStaff(null);
                }}
                className="px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white text-xs font-semibold shadow-sm cursor-pointer"
              >
                Confirm Delete
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
