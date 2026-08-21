import React from 'react';

export type PaymentLogoType =
  | 'curlec'
  | 'paypal'
  | 'stripe'
  | 'billplz'
  | 'toyyibpay'
  | 'senangpay'
  | 'razer_merchant'
  | 'tng_ewallet'
  | 'grabpay'
  | 'atome'
  | 'fpx'
  | 'duitnow'
  | 'maybank'
  | 'cimb'
  | 'public_bank'
  | 'rhb'
  | 'hong_leong'
  | 'ambank'
  | 'bank_islam'
  | 'visa'
  | 'mastercard'
  | 'apple_pay'
  | 'google_pay'
  | 'cod'
  | 'bank_transfer';

interface PaymentLogoProps {
  type: PaymentLogoType | string;
  size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl';
  variant?: 'badge' | 'icon' | 'full' | 'banner';
  className?: string;
  showName?: boolean;
}

export const PaymentLogo: React.FC<PaymentLogoProps> = ({
  type,
  size = 'md',
  variant = 'badge',
  className = '',
  showName = false
}) => {
  const normalizedType = type.toLowerCase().replace(/[-_ ]+/g, '_');

  const sizeClasses = {
    xs: 'w-6 h-6 text-[9px]',
    sm: 'w-8 h-8 text-[10px]',
    md: 'w-11 h-11 text-xs',
    lg: 'w-14 h-14 text-sm',
    xl: 'w-20 h-20 text-base',
  };

  const badgeSizeClasses = {
    xs: 'h-6 px-1.5 py-0.5 text-[9px]',
    sm: 'h-8 px-2.5 py-1 text-[11px]',
    md: 'h-11 px-3.5 py-1.5 text-xs',
    lg: 'h-14 px-4 py-2 text-sm',
    xl: 'h-20 px-6 py-3 text-base',
  };

  // 1. Curlec (by Razorpay) Official Vector Logo
  if (normalizedType === 'curlec') {
    if (variant === 'full' || variant === 'banner') {
      return (
        <div className={`inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-[#091E42] text-white border border-[#1A365D] ${className}`}>
          <svg className="w-6 h-6 flex-shrink-0" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect width="48" height="48" rx="10" fill="#0C2340" />
            <path d="M24 10C16.268 10 10 16.268 10 24C10 31.732 16.268 38 24 38C31.732 38 38 31.732 38 24" stroke="#3395FF" strokeWidth="4" strokeLinecap="round" />
            <path d="M24 16C19.5817 16 16 19.5817 16 24C16 28.4183 19.5817 32 24 32C28.4183 32 32 28.4183 32 24" stroke="#00C9FF" strokeWidth="3.5" strokeLinecap="round" />
            <circle cx="24" cy="24" r="3.5" fill="#52B6FF" />
            <path d="M34 14L38 24L28 22" fill="#3395FF" />
          </svg>
          <div className="flex flex-col">
            <span className="font-extrabold text-sm tracking-tight text-white font-sans">curlec</span>
            <span className="text-[9px] text-[#52B6FF] tracking-wider uppercase font-semibold">by Razorpay</span>
          </div>
        </div>
      );
    }
    return (
      <div className={`rounded-xl bg-[#091E42] flex items-center justify-center p-1.5 border border-[#1B3560] shadow-xs flex-shrink-0 ${sizeClasses[size]} ${className}`} title="Curlec by Razorpay">
        <svg className="w-full h-full" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M24 9C15.716 9 9 15.716 9 24C9 32.284 15.716 39 24 39C32.284 39 39 32.284 39 24" stroke="#3395FF" strokeWidth="4.5" strokeLinecap="round" />
          <path d="M24 16C19.582 16 16 19.582 16 24C16 28.418 19.582 32 24 32C28.418 32 32 28.418 32 24" stroke="#00D2FF" strokeWidth="4" strokeLinecap="round" />
          <circle cx="24" cy="24" r="3.5" fill="#FFFFFF" />
          <polygon points="39,12 41,23 31,20" fill="#3395FF" />
        </svg>
      </div>
    );
  }

  // 2. PayPal Official Double-P Logo
  if (normalizedType === 'paypal') {
    if (variant === 'full' || variant === 'banner') {
      return (
        <div className={`inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-[#003087] text-white border border-[#00246B] shadow-xs ${className}`}>
          <svg className="w-6 h-6 flex-shrink-0" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M24.2 9.5C23.6 6.8 21.3 5 18 5H9.5C8.7 5 8 5.6 7.8 6.4L4.1 25.5C4 26.1 4.4 26.6 5 26.6H9.2L10.3 20.2C10.5 19.4 11.2 18.8 12 18.8H14.5C19.2 18.8 22.8 16.9 23.8 11.7C24.1 10.9 24.2 10.2 24.2 9.5Z" fill="#0079C1" />
            <path d="M26.2 11.5C25.6 8.8 23.3 7 20 7H11.5C10.7 7 10 7.6 9.8 8.4L6.1 27.5C6 28.1 6.4 28.6 7 28.6H11.2L12.3 22.2C12.5 21.4 13.2 20.8 14 20.8H16.5C21.2 20.8 24.8 18.9 25.8 13.7C26.1 12.9 26.2 12.2 26.2 11.5Z" fill="#00457C" opacity="0.3" />
            <path d="M21.2 14.5C20.6 11.8 18.3 10 15 10H8.5C7.7 10 7 10.6 6.8 11.4L3.1 30.5C3 31.1 3.4 31.6 4 31.6H8.2L9.3 25.2C9.5 24.4 10.2 23.8 11 23.8H13.5C18.2 23.8 21.8 21.9 22.8 16.7C23.1 15.9 23.2 15.2 21.2 14.5Z" fill="#0079C1" />
            <path d="M21.2 14.5C20.6 11.8 18.3 10 15 10H8.5C7.7 10 7 10.6 6.8 11.4L4.8 22C4.9 22.2 5.1 22.4 5.3 22.4H8.2L9.3 16.2C9.5 15.4 10.2 14.8 11 14.8H13.5C18.2 14.8 21.8 12.9 22.8 7.7C22.3 10.1 21.2 12.5 18.8 14.5H21.2Z" fill="#00457C" />
          </svg>
          <span className="font-extrabold text-sm tracking-tight text-white">PayPal</span>
        </div>
      );
    }
    return (
      <div className={`rounded-xl bg-[#003087] flex items-center justify-center p-2 border border-[#00246B] shadow-xs flex-shrink-0 ${sizeClasses[size]} ${className}`} title="PayPal Commerce">
        <svg className="w-full h-full" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M26.5 10.2C25.8 7.1 23.1 5 19.2 5H9.5C8.6 5 7.8 5.7 7.6 6.6L3.2 28.5C3.1 29.2 3.6 29.8 4.3 29.8H9.2L10.5 22.5C10.7 21.6 11.5 21 12.4 21H15.2C20.7 21 24.8 18.8 26 12.8C26.4 11.9 26.5 11 26.5 10.2Z" fill="#0079C1" />
          <path d="M22.8 14.5C22.1 11.4 19.4 9.3 15.5 9.3H7.5C6.6 9.3 5.8 10 5.6 10.9L2.2 32.5C2.1 33.2 2.6 33.8 3.3 33.8H8.2L9.5 26.5C9.7 25.6 10.5 25 11.4 25H14.2C19.7 25 23.8 22.8 25 16.8C24.7 15.9 23.8 15.1 22.8 14.5Z" fill="#00457C" />
          <path d="M19.5 14.2C18.8 11.1 16.1 9 12.2 9H6.5C5.6 9 4.8 9.7 4.6 10.6L2.2 26C3 24.5 4.5 23.5 6.2 23.5H9.5L10.5 17.5C10.7 16.6 11.5 16 12.4 16H15.2C18.5 16 21 15 22 12.5C21.4 13.2 20.5 13.8 19.5 14.2Z" fill="#0079C1" />
        </svg>
      </div>
    );
  }

  // 3. Stripe Official Logo
  if (normalizedType === 'stripe') {
    if (variant === 'full' || variant === 'banner') {
      return (
        <div className={`inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-[#635BFF] text-white border border-[#4F46E5] shadow-xs ${className}`}>
          <svg className="w-5 h-5 flex-shrink-0" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M18.8 16.2C18.8 14.9 19.9 14 21.6 14C23.6 14 26.2 14.7 28.1 15.8V10.2C25.9 9.3 23.6 8.9 21.4 8.9C15.6 8.9 11.8 12 11.8 16.7C11.8 24.1 21.9 22.9 21.9 26.3C21.9 27.8 20.4 28.6 18.5 28.6C16.1 28.6 13.1 27.5 11 26.2V31.9C13.4 33 16.1 33.6 18.7 33.6C24.7 33.6 28.9 30.7 28.9 25.8C28.8 18 18.8 19.6 18.8 16.2Z" fill="white" />
          </svg>
          <span className="font-extrabold text-sm tracking-tight text-white font-sans">stripe</span>
        </div>
      );
    }
    return (
      <div className={`rounded-xl bg-[#635BFF] flex items-center justify-center p-2 border border-[#4F46E5] shadow-xs flex-shrink-0 ${sizeClasses[size]} ${className}`} title="Stripe Payments">
        <svg className="w-full h-full" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M18.8 16.2C18.8 14.9 19.9 14 21.6 14C23.6 14 26.2 14.7 28.1 15.8V10.2C25.9 9.3 23.6 8.9 21.4 8.9C15.6 8.9 11.8 12 11.8 16.7C11.8 24.1 21.9 22.9 21.9 26.3C21.9 27.8 20.4 28.6 18.5 28.6C16.1 28.6 13.1 27.5 11 26.2V31.9C13.4 33 16.1 33.6 18.7 33.6C24.7 33.6 28.9 30.7 28.9 25.8C28.8 18 18.8 19.6 18.8 16.2Z" fill="white" />
        </svg>
      </div>
    );
  }

  // 4. Billplz Official FPX Gateway Logo
  if (normalizedType === 'billplz') {
    if (variant === 'full' || variant === 'banner') {
      return (
        <div className={`inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-[#00A651] text-white border border-[#008A43] shadow-xs ${className}`}>
          <div className="w-6 h-6 rounded-lg bg-white flex items-center justify-center text-[#00A651] font-black text-xs">
            ⚡
          </div>
          <div className="flex flex-col leading-none">
            <span className="font-extrabold text-sm tracking-tight text-white">billplz</span>
            <span className="text-[8px] text-emerald-100 uppercase tracking-widest font-bold">FPX Direct</span>
          </div>
        </div>
      );
    }
    return (
      <div className={`rounded-xl bg-[#00A651] flex items-center justify-center p-2 border border-[#008A43] shadow-xs flex-shrink-0 ${sizeClasses[size]} ${className}`} title="Billplz FPX">
        <svg className="w-full h-full" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="20" cy="20" r="18" fill="#008A43" />
          <path d="M12 12H22C26.4 12 29 14.5 29 18.5C29 22.5 26.4 25 22 25H17V30H12V12Z" fill="white" />
          <path d="M17 16H21.5C23.5 16 24.5 17 24.5 18.5C24.5 20 23.5 21 21.5 21H17V16Z" fill="#00A651" />
          <polygon points="27,10 32,18 26,18 29,26 21,17 26,17" fill="#FACC15" />
        </svg>
      </div>
    );
  }

  // 5. ToyyibPay Official Logo
  if (normalizedType === 'toyyibpay' || normalizedType === 'toyyib') {
    if (variant === 'full' || variant === 'banner') {
      return (
        <div className={`inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-[#1B2A4A] text-white border border-[#2D4373] shadow-xs ${className}`}>
          <div className="w-6 h-6 rounded-lg bg-[#FF6B00] flex items-center justify-center text-white font-black text-xs">
            TP
          </div>
          <div className="flex flex-col leading-none">
            <span className="font-extrabold text-sm tracking-tight text-white">toyyib<span className="text-[#FF6B00]">Pay</span></span>
            <span className="text-[8px] text-neutral-300 uppercase tracking-widest font-semibold">FPX Gateway</span>
          </div>
        </div>
      );
    }
    return (
      <div className={`rounded-xl bg-[#1B2A4A] flex items-center justify-center p-1.5 border border-[#2D4373] shadow-xs flex-shrink-0 ${sizeClasses[size]} ${className}`} title="ToyyibPay FPX">
        <svg className="w-full h-full" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect width="40" height="40" rx="8" fill="#1B2A4A" />
          <circle cx="20" cy="20" r="14" fill="#FF6B00" />
          <path d="M14 15H26V19H22V28H18V19H14V15Z" fill="white" />
          <path d="M22 19H25C26.5 19 27.5 20 27.5 21.5C27.5 23 26.5 24 25 24H22V19Z" fill="#1B2A4A" />
        </svg>
      </div>
    );
  }

  // 6. SenangPay Official Logo
  if (normalizedType === 'senangpay' || normalizedType === 'senang_pay') {
    if (variant === 'full' || variant === 'banner') {
      return (
        <div className={`inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-[#00A859] text-white border border-[#008F4C] shadow-xs ${className}`}>
          <div className="w-6 h-6 rounded-lg bg-white flex items-center justify-center text-[#00A859] font-black text-xs">
            S
          </div>
          <div className="flex flex-col leading-none">
            <span className="font-extrabold text-sm tracking-tight text-white">senang<span className="text-yellow-300">Pay</span></span>
            <span className="text-[8px] text-emerald-100 uppercase tracking-widest font-semibold">Cards & FPX</span>
          </div>
        </div>
      );
    }
    return (
      <div className={`rounded-xl bg-[#00A859] flex items-center justify-center p-2 border border-[#008F4C] shadow-xs flex-shrink-0 ${sizeClasses[size]} ${className}`} title="SenangPay">
        <svg className="w-full h-full" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect width="40" height="40" rx="8" fill="#00A859" />
          <path d="M12 26C14 29 18 31 22 30C26 29 29 25 28 21C27 17 21 16 19 14C17 12 18 10 20 10C22 10 24 11 25 13" stroke="white" strokeWidth="3.5" strokeLinecap="round" />
          <circle cx="28" cy="11" r="3" fill="#FACC15" />
        </svg>
      </div>
    );
  }

  // 7. Razer Merchant Services (RMS / MOLPay)
  if (normalizedType === 'razer_merchant' || normalizedType === 'razer' || normalizedType === 'rms' || normalizedType === 'molpay') {
    if (variant === 'full' || variant === 'banner') {
      return (
        <div className={`inline-flex items-center gap-2.5 px-3.5 py-1.5 rounded-xl bg-black text-white border border-neutral-800 shadow-xs ${className}`}>
          <div className="w-6 h-6 rounded-lg bg-[#00FF00]/20 flex items-center justify-center border border-[#00FF00]/40">
            <svg className="w-4 h-4 text-[#00FF00]" viewBox="0 0 24 24" fill="currentColor">
              <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 14.5h-2v-2h2v2zm0-4h-2V7h2v5.5z" />
            </svg>
          </div>
          <div className="flex flex-col leading-none">
            <span className="font-black text-xs tracking-wider text-[#00FF00] uppercase font-mono">RAZER</span>
            <span className="text-[8px] text-neutral-400 font-semibold tracking-tight">Merchant Services</span>
          </div>
        </div>
      );
    }
    return (
      <div className={`rounded-xl bg-[#0B0E14] flex items-center justify-center p-2 border border-[#00FF00]/40 shadow-xs flex-shrink-0 ${sizeClasses[size]} ${className}`} title="Razer Merchant Services">
        <svg className="w-full h-full" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect width="40" height="40" rx="8" fill="#0A0A0A" />
          <path d="M20 7C14 7 11 11 11 15C11 20 18 20 18 24C18 26 16 27 14 26C12 25 11 23 11 23" stroke="#00FF00" strokeWidth="2.5" strokeLinecap="round" />
          <path d="M20 7C26 7 29 11 29 15C29 20 22 20 22 24C22 26 24 27 26 26C28 25 29 23 29 23" stroke="#00FF00" strokeWidth="2.5" strokeLinecap="round" />
          <path d="M20 12V32" stroke="#00FF00" strokeWidth="2.5" strokeLinecap="round" />
          <circle cx="20" cy="7" r="2" fill="#00FF00" />
        </svg>
      </div>
    );
  }

  // 8. Touch 'n Go eWallet Official Logo
  if (normalizedType === 'tng_ewallet' || normalizedType === 'tng' || normalizedType === 'touch_n_go' || normalizedType === 'touchngo') {
    if (variant === 'full' || variant === 'banner') {
      return (
        <div className={`inline-flex items-center gap-2.5 px-3.5 py-1.5 rounded-xl bg-[#005BAA] text-white border border-[#004785] shadow-xs ${className}`}>
          <div className="w-6 h-6 rounded-full bg-white flex items-center justify-center font-black text-[#005BAA] text-[10px]">
            TNG
          </div>
          <div className="flex flex-col leading-none">
            <span className="font-black text-xs tracking-tight text-white">Touch 'n Go</span>
            <span className="text-[8px] text-yellow-300 font-bold tracking-wider uppercase">eWallet</span>
          </div>
        </div>
      );
    }
    return (
      <div className={`rounded-xl bg-[#005BAA] flex items-center justify-center p-1.5 border border-[#004785] shadow-xs flex-shrink-0 ${sizeClasses[size]} ${className}`} title="Touch 'n Go eWallet">
        <svg className="w-full h-full" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect width="40" height="40" rx="8" fill="#005BAA" />
          <circle cx="20" cy="20" r="14" fill="#013A6B" />
          <text x="20" y="17" fill="white" fontSize="8" fontWeight="900" textAnchor="middle" fontFamily="sans-serif">Touch</text>
          <text x="20" y="23" fill="#FACC15" fontSize="7" fontWeight="900" textAnchor="middle" fontFamily="sans-serif">'n Go</text>
          <text x="20" y="29" fill="white" fontSize="5.5" fontWeight="700" textAnchor="middle" fontFamily="sans-serif">eWallet</text>
        </svg>
      </div>
    );
  }

  // 9. GrabPay Official Logo
  if (normalizedType === 'grabpay' || normalizedType === 'grab') {
    if (variant === 'full' || variant === 'banner') {
      return (
        <div className={`inline-flex items-center gap-2.5 px-3.5 py-1.5 rounded-xl bg-[#00B14F] text-white border border-[#008F40] shadow-xs ${className}`}>
          <div className="w-6 h-6 rounded-full bg-white flex items-center justify-center font-black text-[#00B14F] text-xs">
            G
          </div>
          <div className="flex flex-col leading-none">
            <span className="font-extrabold text-sm tracking-tight text-white">Grab<span className="font-normal text-emerald-100">Pay</span></span>
            <span className="text-[8px] text-emerald-100 uppercase tracking-wider font-semibold">Wallet & PayLater</span>
          </div>
        </div>
      );
    }
    return (
      <div className={`rounded-xl bg-[#00B14F] flex items-center justify-center p-2 border border-[#008F40] shadow-xs flex-shrink-0 ${sizeClasses[size]} ${className}`} title="GrabPay">
        <svg className="w-full h-full" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect width="40" height="40" rx="8" fill="#00B14F" />
          <path d="M22 12C16 12 12 16 12 21C12 26 16 29 21 29C25 29 28 26 28 22H21V19H31C31.5 20.5 32 22 32 23.5C32 29 27 33 21 33C14 33 8 27.5 8 20.5C8 13.5 14 8 21.5 8C25.5 8 29 9.5 31.5 12.5L28.5 15C26.5 13 24.5 12 22 12Z" fill="white" />
        </svg>
      </div>
    );
  }

  // 10. Atome BNPL Official Logo
  if (normalizedType === 'atome' || normalizedType === 'atome_bnpl') {
    if (variant === 'full' || variant === 'banner') {
      return (
        <div className={`inline-flex items-center gap-2.5 px-3.5 py-1.5 rounded-xl bg-[#232323] text-white border border-neutral-700 shadow-xs ${className}`}>
          <div className="flex items-center gap-0.5">
            <div className="w-2.5 h-2.5 rounded-full bg-[#E1FF00]"></div>
            <div className="w-2.5 h-2.5 rounded-full bg-[#E1FF00]"></div>
            <div className="w-2.5 h-2.5 rounded-full bg-[#E1FF00]"></div>
          </div>
          <div className="flex flex-col leading-none">
            <span className="font-black text-sm tracking-widest text-[#E1FF00] lowercase font-sans">atome</span>
            <span className="text-[8px] text-neutral-400 uppercase tracking-widest font-bold">BNPL 3x 0%</span>
          </div>
        </div>
      );
    }
    return (
      <div className={`rounded-xl bg-[#232323] flex items-center justify-center p-2 border border-neutral-700 shadow-xs flex-shrink-0 ${sizeClasses[size]} ${className}`} title="Atome BNPL">
        <svg className="w-full h-full" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect width="40" height="40" rx="8" fill="#1C1C1E" />
          <circle cx="12" cy="20" r="4.5" fill="#E1FF00" />
          <circle cx="20" cy="20" r="4.5" fill="#E1FF00" />
          <circle cx="28" cy="20" r="4.5" fill="#E1FF00" />
        </svg>
      </div>
    );
  }

  // 11. FPX Official Malaysian National Logo
  if (normalizedType === 'fpx' || normalizedType === 'online_banking_fpx') {
    return (
      <div className={`rounded-xl bg-white flex items-center justify-center p-1.5 border border-neutral-200 dark:border-neutral-700 shadow-xs flex-shrink-0 ${sizeClasses[size]} ${className}`} title="FPX Online Banking Malaysia">
        <svg className="w-full h-full" viewBox="0 0 48 32" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M14 6C8 6 4 10 4 16C4 22 8 26 14 26C18 26 21 24 23 21H18C15 21 13 19 13 16C13 13 15 11 18 11H25C23 8 19 6 14 6Z" fill="#004B87" />
          <path d="M34 26C40 26 44 22 44 16C44 10 40 6 34 6C30 6 27 8 25 11H30C33 11 35 13 35 16C35 19 33 21 30 21H23C25 24 29 26 34 26Z" fill="#E31837" />
          <circle cx="24" cy="16" r="3.5" fill="#E31837" />
        </svg>
      </div>
    );
  }

  // 12. DuitNow National QR Official Logo
  if (normalizedType === 'duitnow' || normalizedType === 'duitnow_qr' || normalizedType === 'qr') {
    return (
      <div className={`rounded-xl bg-[#ED008C] flex items-center justify-center p-1.5 border border-[#C50075] shadow-xs flex-shrink-0 ${sizeClasses[size]} ${className}`} title="DuitNow QR">
        <svg className="w-full h-full" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect width="40" height="40" rx="8" fill="#ED008C" />
          <polygon points="10,12 22,12 16,20" fill="white" />
          <polygon points="18,20 30,12 30,28" fill="white" />
          <polygon points="10,28 22,28 16,20" fill="white" />
          <polygon points="12,18 18,18 15,22" fill="#ED008C" />
        </svg>
      </div>
    );
  }

  // 13. Malaysian Bank: Maybank
  if (normalizedType === 'maybank' || normalizedType.includes('maybank') || normalizedType === 'mbb') {
    return (
      <div className={`rounded-xl bg-[#FFC800] flex items-center justify-center p-1.5 border border-[#E5B400] shadow-xs flex-shrink-0 ${sizeClasses[size]} ${className}`} title="Maybank2u / Malayan Banking Berhad">
        <svg className="w-full h-full" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect width="40" height="40" rx="8" fill="#FFC800" />
          <circle cx="20" cy="20" r="13" fill="#1C1917" />
          <path d="M14 16C15 13 18 12 21 12C24 12 27 14 27 17C27 19 25 21 22 21L25 27H21L19 22H17V27H14V16Z" fill="#FFC800" />
          <circle cx="18" cy="16" r="1.5" fill="#1C1917" />
        </svg>
      </div>
    );
  }

  // 14. Malaysian Bank: CIMB
  if (normalizedType === 'cimb' || normalizedType.includes('cimb')) {
    return (
      <div className={`rounded-xl bg-[#ED1B24] flex items-center justify-center p-1.5 border border-[#C51119] shadow-xs flex-shrink-0 ${sizeClasses[size]} ${className}`} title="CIMB Clicks / CIMB Bank">
        <svg className="w-full h-full" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect width="40" height="40" rx="8" fill="#ED1B24" />
          <polygon points="20,7 32,12 35,24 28,33 12,33 5,24 8,12" fill="white" />
          <text x="20" y="23" fill="#ED1B24" fontSize="8" fontWeight="900" textAnchor="middle" fontFamily="sans-serif">CIMB</text>
        </svg>
      </div>
    );
  }

  // 15. Malaysian Bank: Public Bank
  if (normalizedType === 'public_bank' || normalizedType.includes('public') || normalizedType === 'pbb') {
    return (
      <div className={`rounded-xl bg-[#ED1C24] flex items-center justify-center p-1.5 border border-[#C7131A] shadow-xs flex-shrink-0 ${sizeClasses[size]} ${className}`} title="Public Bank Berhad (PBe)">
        <svg className="w-full h-full" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect width="40" height="40" rx="8" fill="#ED1C24" />
          <rect x="8" y="8" width="24" height="24" rx="4" fill="white" />
          <polygon points="20,10 23,17 30,20 23,23 20,30 17,23 10,20 17,17" fill="#ED1C24" />
        </svg>
      </div>
    );
  }

  // 16. Malaysian Bank: RHB Bank
  if (normalizedType === 'rhb' || normalizedType.includes('rhb')) {
    return (
      <div className={`rounded-xl bg-[#004C97] flex items-center justify-center p-1.5 border border-[#003B75] shadow-xs flex-shrink-0 ${sizeClasses[size]} ${className}`} title="RHB Now / RHB Bank">
        <svg className="w-full h-full" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect width="40" height="40" rx="8" fill="#004C97" />
          <circle cx="20" cy="20" r="13" fill="#E5007D" />
          <text x="20" y="24" fill="white" fontSize="10" fontWeight="900" textAnchor="middle" fontFamily="sans-serif">RHB</text>
        </svg>
      </div>
    );
  }

  // 17. Malaysian Bank: Hong Leong Bank
  if (normalizedType === 'hong_leong' || normalizedType.includes('hong_leong') || normalizedType === 'hlb') {
    return (
      <div className={`rounded-xl bg-[#002B49] flex items-center justify-center p-1.5 border border-[#001D33] shadow-xs flex-shrink-0 ${sizeClasses[size]} ${className}`} title="Hong Leong Connect">
        <svg className="w-full h-full" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect width="40" height="40" rx="8" fill="#002B49" />
          <circle cx="20" cy="20" r="12" fill="#ED1C24" />
          <text x="20" y="23" fill="white" fontSize="9" fontWeight="900" textAnchor="middle" fontFamily="sans-serif">HLB</text>
        </svg>
      </div>
    );
  }

  // 18. Visa Card
  if (normalizedType === 'visa') {
    return (
      <div className={`rounded-xl bg-[#1A1F71] flex items-center justify-center p-1.5 border border-[#131754] shadow-xs flex-shrink-0 ${sizeClasses[size]} ${className}`} title="Visa">
        <svg className="w-full h-full" viewBox="0 0 36 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect width="36" height="24" rx="4" fill="#1A1F71" />
          <text x="18" y="16" fill="white" fontSize="11" fontWeight="900" fontStyle="italic" textAnchor="middle" fontFamily="sans-serif">VISA</text>
        </svg>
      </div>
    );
  }

  // 19. Mastercard
  if (normalizedType === 'mastercard') {
    return (
      <div className={`rounded-xl bg-[#111] flex items-center justify-center p-1.5 border border-neutral-800 shadow-xs flex-shrink-0 ${sizeClasses[size]} ${className}`} title="Mastercard">
        <svg className="w-full h-full" viewBox="0 0 36 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect width="36" height="24" rx="4" fill="#1F2937" />
          <circle cx="14" cy="12" r="7" fill="#EB001B" />
          <circle cx="22" cy="12" r="7" fill="#F79E1B" fillOpacity="0.9" />
        </svg>
      </div>
    );
  }

  // 20. Apple Pay
  if (normalizedType === 'apple_pay' || normalizedType === 'applepay') {
    return (
      <div className={`rounded-xl bg-black flex items-center justify-center p-1.5 border border-neutral-800 shadow-xs flex-shrink-0 ${sizeClasses[size]} ${className}`} title="Apple Pay">
        <svg className="w-full h-full" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect width="40" height="40" rx="8" fill="black" />
          <path d="M17.5 13C16.8 13.8 15.8 14.4 14.7 14.3C14.5 13.3 15 12.2 15.6 11.5C16.3 10.7 17.5 10.1 18.5 10C18.6 11.1 18.1 12.2 17.5 13ZM18.5 14.8C16.9 14.7 15.5 15.7 14.7 15.7C13.9 15.7 12.7 14.8 11.5 14.9C9.9 14.9 8.4 15.8 7.6 17.2C5.9 20.2 7.2 24.6 8.8 27C9.6 28.1 10.5 29.4 11.8 29.3C13 29.2 13.5 28.5 15 28.5C16.5 28.5 16.9 29.3 18.2 29.3C19.5 29.3 20.3 28.1 21.1 27C22 25.7 22.4 24.4 22.5 24.3C22.4 24.2 19.9 23.3 19.9 20.4C19.9 18 21.8 16.8 21.9 16.7C20.8 15.1 19.1 14.9 18.5 14.8Z" fill="white" />
          <text x="30" y="24" fill="white" fontSize="9" fontWeight="700" textAnchor="middle" fontFamily="sans-serif">Pay</text>
        </svg>
      </div>
    );
  }

  // 21. Google Pay
  if (normalizedType === 'google_pay' || normalizedType === 'gpay') {
    return (
      <div className={`rounded-xl bg-white flex items-center justify-center p-1.5 border border-neutral-200 dark:border-neutral-700 shadow-xs flex-shrink-0 ${sizeClasses[size]} ${className}`} title="Google Pay">
        <svg className="w-full h-full" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect width="40" height="40" rx="8" fill="white" />
          <path d="M19.5 20.2V16.8H26.2C26.4 17.5 26.5 18.2 26.5 19.1C26.5 23.2 23.8 26 19.5 26C15.9 26 13 23.1 13 19.5C13 15.9 15.9 13 19.5 13C21.3 13 22.8 13.6 23.9 14.7L21.4 17.2C20.8 16.6 20.3 16.3 19.5 16.3C17.7 16.3 16.3 17.7 16.3 19.5C16.3 21.3 17.7 22.7 19.5 22.7C21.6 22.7 22.6 21.3 22.8 20.2H19.5Z" fill="#4285F4" />
        </svg>
      </div>
    );
  }

  // 22. Cash on Delivery (COD)
  if (normalizedType === 'cod' || normalizedType === 'cash_on_delivery') {
    return (
      <div className={`rounded-xl bg-emerald-600 flex items-center justify-center p-2 border border-emerald-700 shadow-xs flex-shrink-0 text-white font-black ${sizeClasses[size]} ${className}`} title="Cash on Delivery">
        <svg className="w-full h-full" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect width="40" height="40" rx="8" fill="#059669" />
          <rect x="6" y="11" width="28" height="18" rx="3" stroke="white" strokeWidth="2.5" />
          <circle cx="20" cy="20" r="4.5" fill="white" />
          <text x="20" y="23" fill="#059669" fontSize="6.5" fontWeight="900" textAnchor="middle" fontFamily="sans-serif">RM</text>
        </svg>
      </div>
    );
  }

  // 23. Manual Bank Transfer
  if (normalizedType === 'bank_transfer' || normalizedType === 'manual_transfer') {
    return (
      <div className={`rounded-xl bg-indigo-600 flex items-center justify-center p-2 border border-indigo-700 shadow-xs flex-shrink-0 text-white ${sizeClasses[size]} ${className}`} title="Manual Bank Transfer">
        <svg className="w-full h-full" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect width="40" height="40" rx="8" fill="#4F46E5" />
          <polygon points="20,9 31,16 9,16" fill="white" />
          <rect x="11" y="18" width="3" height="9" fill="white" />
          <rect x="18.5" y="18" width="3" height="9" fill="white" />
          <rect x="26" y="18" width="3" height="9" fill="white" />
          <rect x="8" y="28" width="24" height="3" rx="1" fill="white" />
        </svg>
      </div>
    );
  }

  // Default fallback badge
  return (
    <div className={`rounded-xl bg-neutral-100 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 flex items-center justify-center font-bold text-neutral-600 dark:text-neutral-300 flex-shrink-0 ${sizeClasses[size]} ${className}`}>
      {type.slice(0, 3).toUpperCase()}
    </div>
  );
};
