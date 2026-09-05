import { useAdminCurrency } from '../../context/useAdminCurrency';
import React, { useState } from 'react';
import { useAdmin } from '../../context/AdminContext';
import { Customer } from '../../types';
import { DeleteConfirmationModal } from '../common/DeleteConfirmationModal';
import {
  Users,
  Search,
  Plus,
  Trash2,
  Edit2,
  Crown,
  ShoppingBag,
  DollarSign,
  Mail,
  Phone,
  ShieldAlert,
  ShieldCheck,
  X,
  MapPin
} from 'lucide-react';

export const CustomerAdmin: React.FC = () => {
  const { money, currencyCode } = useAdminCurrency();
  const { customers, saveCustomer, deleteCustomer, orders } = useAdmin();

  const [searchTerm, setSearchTerm] = useState('');
  const [selectedStatus, setSelectedStatus] = useState<string>('all');
  const [editingCustomer, setEditingCustomer] = useState<Customer | null>(null);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [customerToDelete, setCustomerToDelete] = useState<Customer | null>(null);

  const filteredCustomers = customers.filter(c => {
    const matchesSearch =
      c.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
      c.email.toLowerCase().includes(searchTerm.toLowerCase());
    const matchesStatus = selectedStatus === 'all' || c.status === selectedStatus;
    return matchesSearch && matchesStatus;
  });

  const openCreateModal = () => {
    const newCust: Customer = {
      id: 'cust-' + Date.now(),
      name: '',
      email: '',
      phone: '+1 (555) 000-0000',
      avatar: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=200&auto=format&fit=crop&q=80',
      totalOrders: 0,
      totalSpent: 0,
      currency: 'USD',
      registeredAt: new Date().toISOString().split('T')[0],
      status: 'active',
      tags: ['new-user'],
      addresses: [
        {
          id: 'addr-' + Date.now(),
          isDefault: true,
          type: 'shipping',
          firstName: 'New',
          lastName: 'Customer',
          address1: '100 Market St',
          city: 'San Francisco',
          province: 'CA',
          country: 'United States',
          postalCode: '94105',
          phone: '+1 555-000-0000'
        }
      ]
    };
    setEditingCustomer(newCust);
    setIsModalOpen(true);
  };

  const renderStatusBadge = (status: Customer['status'] | null) => {
    if (status === null) return null;

    switch (status) {
      case 'vip':
        return <span className="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-purple-100 dark:bg-purple-950 text-purple-700 dark:text-purple-300 border border-purple-300"><Crown className="w-2.5 h-2.5" /> VIP Member</span>;
      case 'blocked':
        return <span className="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-rose-100 dark:bg-rose-950 text-rose-700 dark:text-rose-300 border border-rose-300">Blocked</span>;
      default:
        return <span className="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 border border-emerald-300">Active</span>;
    }
  };

  return (
    <div className="space-y-6 pb-12">
      {/* Header Bar */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-5 rounded-3xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-sm">
        <div>
          <div className="flex items-center gap-2">
            <span className="px-2.5 py-0.5 text-[10px] font-bold uppercase rounded-full bg-pink-500/20 text-pink-700 dark:text-pink-300 border border-pink-500/30">
              Customer Intelligence
            </span>
            <span className="text-xs text-neutral-500">Accounts & Profiles</span>
          </div>
          <h2 className="text-lg sm:text-xl font-bold text-neutral-900 dark:text-white tracking-tight mt-1">
            Customer Directory & Accounts
          </h2>
          <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
            Monitor registered buyer profiles, VIP membership status, lifetime spend, and address book records.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <button
            onClick={openCreateModal}
            className="px-3.5 py-2 rounded-xl bg-pink-600 hover:bg-pink-700 text-white text-xs font-bold transition-all shadow-md shadow-pink-500/20 flex items-center gap-1.5"
          >
            <Plus className="w-4 h-4" />
            <span>Add Customer</span>
          </button>
        </div>
      </div>

      {/* Filter Bar */}
      <div className="p-4 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs flex flex-col md:flex-row items-center justify-between gap-3">
        <div className="relative w-full md:w-80">
          <Search className="w-4 h-4 text-neutral-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
          <input
            type="text"
            placeholder="Search name or email address..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="w-full pl-9 pr-4 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
          />
        </div>

        <div className="flex items-center gap-2">
          <select
            value={selectedStatus}
            onChange={(e) => setSelectedStatus(e.target.value)}
            className="px-3 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
          >
            <option value="all">All Customer Statuses</option>
            <option value="active">Active</option>
            <option value="vip">VIP Members</option>
            <option value="blocked">Blocked / Suspended</option>
          </select>
        </div>
      </div>

      {/* Customers Table */}
      <div className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-3xl overflow-hidden shadow-sm">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs border-collapse">
            <thead>
              <tr className="border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50/70 dark:bg-neutral-800/60 text-[10px] uppercase font-bold text-neutral-500 tracking-wider">
                <th className="py-3.5 px-4">Customer Profile</th>
                <th className="py-3.5 px-4">Account Status</th>
                <th className="py-3.5 px-4">Orders Placed</th>
                <th className="py-3.5 px-4">Lifetime Spend</th>
                <th className="py-3.5 px-4">Tags</th>
                <th className="py-3.5 px-4">Registered Date</th>
                <th className="py-3.5 px-4 text-right">Action</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800 text-neutral-800 dark:text-neutral-200">
              {filteredCustomers.map((cust) => (
                <tr key={cust.id} className="hover:bg-neutral-50/60 dark:hover:bg-neutral-800/50 transition-colors">
                  <td className="py-3.5 px-4">
                    <div className="flex items-center gap-3">
                      {cust.avatar ? <img src={cust.avatar} alt={cust.name} className="w-9 h-9 rounded-full object-cover ring-1 ring-neutral-200 dark:ring-neutral-700" /> : null}
                      <div>
                        <div className="font-bold text-neutral-900 dark:text-white text-xs">{cust.name}</div>
                        <div className="text-[11px] text-neutral-400 font-mono">{cust.email}</div>
                      </div>
                    </div>
                  </td>

                  <td className="py-3.5 px-4">
                    {renderStatusBadge(cust.status)}
                  </td>

                  <td className="py-3.5 px-4 font-mono font-semibold">
                    {cust.totalOrders} orders
                  </td>

                  <td className="py-3.5 px-4 font-mono font-bold text-neutral-900 dark:text-white text-xs">
                    {money(cust.totalSpent)}
                  </td>

                  <td className="py-3.5 px-4">
                    <div className="flex flex-wrap gap-1">
                      {(cust.tags || []).map(t => (
                        <span key={t} className="text-[9px] font-medium px-1.5 py-0.2 rounded bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400">
                          {t}
                        </span>
                      ))}
                    </div>
                  </td>

                  <td className="py-3.5 px-4 font-mono text-[11px] text-neutral-400">
                    {cust.registeredAt}
                  </td>

                  <td className="py-3.5 px-4 text-right">
                    <div className="flex items-center justify-end gap-1.5">
                      <button
                        onClick={() => {
                          const editableCustomer = customers.find((customer) => customer.id === cust.id);
                          if (editableCustomer) {
                            setEditingCustomer(editableCustomer);
                            setIsModalOpen(true);
                          }
                        }}
                        className="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800"
                      >
                        <Edit2 className="w-3.5 h-3.5" />
                      </button>
                      <button
                        onClick={() => setCustomerToDelete(cust)}
                        className="p-1.5 rounded-lg text-rose-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30"
                      >
                        <Trash2 className="w-3.5 h-3.5" />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* Delete Customer Confirmation Modal */}
      <DeleteConfirmationModal
        isOpen={!!customerToDelete}
        itemType="Customer Account"
        itemName={customerToDelete?.name}
        itemDetails={`Email: ${customerToDelete?.email} • Total Orders: ${customerToDelete?.totalOrders} • Lifetime Spend: ${money(customerToDelete?.totalSpent)}`}
        warningMessage={`Are you sure you want to permanently delete the profile for "${customerToDelete?.name}"?`}
        impactItems={[
          'Customer login credentials and shipping address book will be removed.',
          'Historical order references will retain anonymized transaction IDs.',
          'VIP membership perks, loyalty points, and store credits will be revoked.',
        ]}
        confirmButtonText="Delete Customer Account"
        onConfirm={async () => {
          if (customerToDelete) {
            await deleteCustomer(customerToDelete.id);
            setCustomerToDelete(null);
          }
        }}
        onClose={() => setCustomerToDelete(null)}
      />

      {/* Customer Edit Modal */}
      {isModalOpen && editingCustomer && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs animate-in fade-in">
          <div className="w-full max-w-md bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-3xl p-6 shadow-2xl">
            <div className="flex items-center justify-between pb-3 border-b border-neutral-100 dark:border-neutral-800 mb-4">
              <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
                {editingCustomer.name ? `Edit Customer: ${editingCustomer.name}` : 'Create Customer'}
              </h3>
              <button onClick={() => setIsModalOpen(false)} className="text-neutral-400 hover:text-neutral-600">
                <X className="w-4 h-4" />
              </button>
            </div>

            <form
              onSubmit={async (e) => {
                e.preventDefault();
                const saved = await saveCustomer(editingCustomer);
                if (saved) setIsModalOpen(false);
              }}
              className="space-y-4"
            >
              <div>
                <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Full Name</label>
                <input
                  type="text"
                  required
                  value={editingCustomer.name}
                  onChange={(e) => setEditingCustomer({ ...editingCustomer, name: e.target.value })}
                  className="w-full px-3 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Email Address</label>
                <input
                  type="email"
                  required
                  value={editingCustomer.email}
                  onChange={(e) => setEditingCustomer({ ...editingCustomer, email: e.target.value })}
                  className="w-full px-3 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white font-mono"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">Account Status</label>
                <select
                  value={editingCustomer.status}
                  onChange={(e) => setEditingCustomer({ ...editingCustomer, status: e.target.value as Customer['status'] })}
                  className="w-full px-3 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
                >
                  <option value="active">Active</option>
                  <option value="vip">VIP Member</option>
                  <option value="blocked">Blocked / Suspended</option>
                </select>
              </div>

              <div className="flex justify-end gap-2 pt-3 border-t border-neutral-100 dark:border-neutral-800">
                <button
                  type="button"
                  onClick={() => setIsModalOpen(false)}
                  className="px-4 py-2 text-xs font-medium rounded-xl border border-neutral-300 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-4 py-2 text-xs font-bold rounded-xl bg-pink-600 hover:bg-pink-700 text-white shadow-md shadow-pink-500/20"
                >
                  Save Profile
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
