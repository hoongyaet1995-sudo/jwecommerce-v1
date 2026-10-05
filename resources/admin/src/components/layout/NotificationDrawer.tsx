import React, { useEffect, useRef } from 'react';
import { useAdmin } from '../../context/AdminContext';
import {
  Bell,
  Globe,
  X,
  CheckCircle2,
  Package,
  ShoppingBag,
  AlertTriangle,
  UserCheck,
  ChevronRight
} from 'lucide-react';

export const NotificationDrawer: React.FC = () => {
  const { isNotificationOpen, setIsNotificationOpen, supportAlerts, supportAlertUnread, supportAlertError, markSupportAlertRead } = useAdmin();
  const previousFocusRef = useRef<HTMLElement | null>(null);

  useEffect(() => {
    if (!isNotificationOpen) return;

    previousFocusRef.current = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    const closeDrawer = () => {
      setIsNotificationOpen(false);
      window.requestAnimationFrame(() => previousFocusRef.current?.focus());
    };
    const handleEscape = (e: KeyboardEvent) => {
      if (e.key !== 'Escape') return;
      e.preventDefault();
      closeDrawer();
    };

    document.addEventListener('keydown', handleEscape);
    return () => document.removeEventListener('keydown', handleEscape);
  }, [isNotificationOpen, setIsNotificationOpen]);

  const closeDrawer = () => {
    setIsNotificationOpen(false);
    window.requestAnimationFrame(() => previousFocusRef.current?.focus());
  };

  if (!isNotificationOpen) return null;

  const notifications=supportAlerts.map(n=>({...n,type:'user',action:async()=>{
    if(await markSupportAlertRead(n.id)) window.location.assign('/admin/support?conversation='+encodeURIComponent(n.conversationId));
  }}));

  return (
    <div className="fixed inset-0 z-50 overflow-hidden bg-black/40 backdrop-blur-xs">
      <div className="absolute inset-y-0 right-0 max-w-full flex pl-10">
        <div
          id="notification-drawer"
          className="w-screen max-w-md bg-white dark:bg-neutral-900 border-l border-neutral-200 dark:border-neutral-800 shadow-2xl flex flex-col animate-in slide-in-from-right duration-200"
        >
          {/* Header */}
          <div className="p-4 sm:p-5 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between">
            <div className="flex items-center gap-2.5">
              <div className="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                <Bell className="w-4 h-4" />
              </div>
              <div>
                <h2 className="text-sm font-bold text-neutral-900 dark:text-white">Live Activity & Alerts</h2>
                <p className="text-[11px] text-neutral-500 dark:text-neutral-400">{supportAlertUnread} unread notifications</p>
              </div>
            </div>
            <button
              onClick={closeDrawer}
              className="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors"
            >
              <X className="w-4 h-4" />
            </button>
          </div>

          {/* List */}
          <div className="flex-1 overflow-y-auto p-4 space-y-3">
            {supportAlertError && <p role="alert" className="text-xs text-rose-600">{supportAlertError}</p>}
            {!supportAlertError && notifications.length === 0 && <p className="text-xs text-neutral-500">No support notifications yet.</p>}
            {notifications.map((n) => {
              let Icon = Bell;
              let iconBg = 'bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400';
              if (n.channel === 'facebook') {
                Icon = MessengerIcon;
                iconBg = 'bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400';
              } else if (n.channel === 'web_guest') {
                Icon = Globe;
                iconBg = 'bg-cyan-100 dark:bg-cyan-950/60 text-cyan-600 dark:text-cyan-400';
              } else if (n.type === 'order') {
                Icon = ShoppingBag;
                iconBg = 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400';
              } else if (n.type === 'stock') {
                Icon = AlertTriangle;
                iconBg = 'bg-amber-100 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400';
              } else if (n.type === 'user') {
                Icon = UserCheck;
                iconBg = 'bg-cyan-100 dark:bg-cyan-950/60 text-cyan-600 dark:text-cyan-400';
              }

              return (
                <div
                  key={n.id}
                  onClick={n.action} role="button" tabIndex={0} onKeyDown={e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();void n.action();}}}
                  className={`group relative p-3.5 rounded-2xl border transition-all cursor-pointer ${
                    n.unread
                      ? 'bg-indigo-50/40 dark:bg-indigo-950/20 border-indigo-200 dark:border-indigo-900/50 hover:border-indigo-300 dark:hover:border-indigo-800'
                      : 'bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-800 hover:border-neutral-300 dark:hover:border-neutral-700'
                  }`}
                >
                  <div className="flex items-start gap-3">
                    <div className={`w-8 h-8 rounded-xl flex items-center justify-center flex-shrink-0 ${iconBg}`}>
                      <Icon className="w-4 h-4" />
                    </div>
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center justify-between gap-2">
                        <p className="text-xs font-bold text-neutral-900 dark:text-white leading-snug">{n.title}</p>
                        {n.unread && <span className="w-2 h-2 rounded-full bg-indigo-600 flex-shrink-0"></span>}
                      </div>
                      <p className="text-[11px] text-neutral-600 dark:text-neutral-400 mt-0.5 leading-relaxed">{n.description}</p>
                      <div className="mt-2 flex items-center justify-between text-[10px] text-neutral-400">
                        <span>{n.time}</span>
                        <span className="flex items-center gap-0.5 text-indigo-600 dark:text-indigo-400 font-medium group-hover:translate-x-0.5 transition-transform">
                          View details <ChevronRight className="w-3 h-3" />
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>

          {/* Footer */}
          <div className="p-4 border-t border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-950 flex items-center justify-between text-xs">
            <span className="text-[11px] text-neutral-500">Updates every 5 seconds</span>
            <button
              onClick={() => void markSupportAlertRead("all")}
              className="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline"
            >
              Mark all as read
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};


function MessengerIcon({className}:{className?:string}) {
  return <svg className={className} viewBox="0 0 24 24" fill="currentColor" role="img" aria-label="Messenger"><path d="M12 2C6.48 2 2 6.14 2 11.25c0 2.91 1.45 5.5 3.72 7.2V22l3.4-1.87c.91.25 1.88.37 2.88.37 5.52 0 10-4.14 10-9.25S17.52 2 12 2Z"/><path d="m5.7 14.2 4.15-4.4 3.15 2.36 5.3-2.96-4.15 4.4L11 11.24l-5.3 2.96Z" fill="white"/></svg>;
}
