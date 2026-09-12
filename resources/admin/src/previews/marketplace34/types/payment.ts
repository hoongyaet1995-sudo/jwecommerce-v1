export type PaymentGatewayId = 
  | 'billplz' 
  | 'toyyibpay' 
  | 'senangpay' 
  | 'razer_merchant' 
  | 'curlec' 
  | 'stripe' 
  | 'paypal' 
  | 'tng_ewallet' 
  | 'grabpay' 
  | 'atome';

export type PaymentMethodCategory = 'online_banking_fpx' | 'card' | 'ewallet' | 'bnpl' | 'offline';

export interface PaymentGatewayConfig {
  id: PaymentGatewayId;
  name: string;
  category: PaymentMethodCategory;
  description: string;
  logo: string;
  isEnabled: boolean;
  isSandbox: boolean;
  sortOrder: number;
  credentials: {
    apiKey?: string;
    secretKey?: string;
    collectionId?: string;
    merchantId?: string;
    publishableKey?: string;
    webhookSecret?: string;
    categoryCode?: string;
    hashType?: 'sha256' | 'md5';
    clientId?: string;
    clientSecret?: string;
    partnerId?: string;
    signatureKey?: string;
    [key: string]: string | undefined;
  };
  supportedCurrencies: string[];
  transactionFee: {
    type: 'fixed' | 'percentage' | 'hybrid';
    fixedAmount?: number; // e.g. RM 1.00
    percentageRate?: number; // e.g. 2.5%
    feePayer: 'merchant' | 'customer'; // Absorbed by store vs passed to customer
  };
  limits: {
    minAmount?: number;
    maxAmount?: number;
  };
  features: {
    supportsRecurring?: boolean;
    supports3DS2?: boolean;
    supportsApplePay?: boolean;
    supportsGooglePay?: boolean;
    supportsQrDeeplink?: boolean;
    instantSettlement?: boolean;
  };
  webhookUrl: string;
  lastTestedAt?: string;
  testStatus?: 'success' | 'failed' | 'untested';
  testMessage?: string;
}

export interface BankAccountDetails {
  id: string;
  bankName: string;
  bankCode: string;
  accountHolderName: string;
  accountNumber: string;
  branchName?: string;
  swiftCode?: string;
  duitNowId?: string;
  duitNowQrUrl?: string;
  isPrimary: boolean;
  isActive: boolean;
}

export interface ManualBankTransferConfig {
  isEnabled: boolean;
  bankAccounts: BankAccountDetails[];
  requireProofUpload: boolean;
  verificationInstructions: string;
  paymentWindowMinutes: number; // e.g. 1440 (24 hours)
  notificationEmail: string;
  allowInstantDuitNow: boolean;
}

export interface CashOnDeliveryConfig {
  isEnabled: boolean;
  surchargeType: 'fixed' | 'percentage';
  surchargeAmount: number; // e.g. RM 5.00 or 3%
  minOrderAmount: number; // e.g. RM 20
  maxOrderAmount: number; // e.g. RM 500
  allowedRegions: 'all' | 'peninsular_only' | 'east_malaysia_only' | 'custom_postcodes';
  customPostcodes: string[];
  requirePhoneOtp: boolean;
  instructions: string;
}

export interface GlobalPaymentSettings {
  defaultGateway: string; // 'billplz' | 'stripe' | 'manual_transfer' | etc.
  allowMultiplePaymentMethods: boolean;
  enableSandboxBanner: boolean;
  unpaidOrderAutoCancelMinutes: number; // e.g. 120 minutes (2 hrs)
  sendPaymentReminderSms: boolean;
  sendPaymentReminderEmail: boolean;
  displayTaxInvoiceAtCheckout: boolean;
  enableLoyaltyPointsSplitPayment: boolean;
  maxLoyaltyPointsDiscountPercent: number; // e.g. 50%
}
